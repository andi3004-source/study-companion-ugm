#!/usr/bin/env python3
"""
Generator audio MP3 statis dari Markdown Buku Saku Tuturnusa menggunakan edge-tts.

PILIHAN MESIN TTS:
- Script ini menggunakan `edge-tts` (voice: id-ID-GadisNeural, rate: -10%).
- PERINGATAN: `edge-tts` menggunakan endpoint layanan Microsoft Edge yang tidak resmi (unofficial API).
- Jika sewaktu-waktu endpoint ini mengalami throttling, perubahan protokol, atau tidak dapat diakses,
  sistem disiapkan untuk beralih ke Gemini TTS / Google Cloud Text-to-Speech API (id-ID-Standard-A atau Wavenet)
  sebagai opsi resmi berbayar yang stabil.

Penggunaan:
    python3 scripts/buku_saku/tts.py
"""

import asyncio
import hashlib
import json
import os
import re
import subprocess
import sys
from pathlib import Path

# Coba import mutagen; sediakan fallback ffprobe jika belum terpasang
try:
    from mutagen.mp3 import MP3
    HAS_MUTAGEN = True
except ImportError:
    HAS_MUTAGEN = False

VOICE = "id-ID-GadisNeural"
RATE = "-10%"

NUM_WORDS = {
    1: "satu", 2: "dua", 3: "tiga", 4: "empat", 5: "lima",
    6: "enam", 7: "tujuh", 8: "delapan", 9: "sembilan", 10: "sepuluh",
    11: "sebelas", 12: "dua belas", 13: "tiga belas", 14: "empat belas",
    15: "lima belas", 16: "enam belas"
}


def parse_front_matter(content: str):
    m = re.match(r"^---\r?\n(.*?)\r?\n---\r?\n(.*)$", content, re.DOTALL)
    if not m:
        return {}, content
    raw_meta, body = m.groups()
    meta = {}
    for line in raw_meta.splitlines():
        if ":" in line:
            k, v = line.split(":", 1)
            meta[k.strip()] = v.strip()
    return meta, body


def strip_citations(text: str) -> str:
    """Membuang kutipan sumber dalam tanda kurung (misalnya (AFB), (RNIB), (Kemenkes RI, 2022a))."""
    # Pola: kurung berisi tahun 4 digit atau nama sumber yang ada di 99-referensi
    text = re.sub(r"\s*\([^)]*(?:AFB|RNIB|NDVS|Battle for Blindness|Blind Welfare Society|Kemenkes|\b\d{4}[a-z]?\b)[^)]*\)", "", text)
    # Rapikan spasi sebelum tanda baca
    text = re.sub(r"\s+([.,;:!?])", r"\1", text)
    return text


def strip_markdown_formatting(text: str) -> str:
    """Menghapus penanda markdown (**, _, backtick) dari teks audio."""
    text = re.sub(r"\*\*([^*]+)\*\*", r"\1", text)
    text = re.sub(r"`([^`]+)`", r"\1", text)
    text = re.sub(r"_([^_]+)_", r"\1", text)
    text = re.sub(r"\*([^*]+)\*", r"\1", text)
    text = re.sub(r"[*`_]", "", text)
    return text


def markdown_to_speech_text(content: str) -> str:
    meta, body = parse_front_matter(content)
    title = meta.get("judul", "")
    section = meta.get("bagian", "")
    is_ref = "referensi" in title.lower() or "referensi" in section.lower()

    lines = []
    if title:
        lines.append(f"{title}.")
    if section and section not in ("Pengantar", "Referensi"):
        lines.append(f"{section}.")
    lines.append("")

    for raw_line in body.splitlines():
        line = raw_line.strip()
        if not line:
            continue
        if line.startswith("### "):
            lines.append(f"{line[4:]}.")
        elif line.startswith("## "):
            heading_title = line[3:].strip()
            lines.append(f"{heading_title}:")
        elif re.match(r"^\d+\.\s+", line):
            step_num = int(re.match(r"^(\d+)\.", line).group(1))
            step_body = re.sub(r"^\d+\.\s+", "", line)
            word = NUM_WORDS.get(step_num, str(step_num))
            if is_ref:
                # 99-referensi: jangan bacakan URL sama sekali, cukup "Nomor satu. <nama sumber>"
                clean_ref = re.sub(r"\s*https?://\S+", "", step_body).strip()
                lines.append(f"Nomor {word}. {clean_ref}")
            else:
                lines.append(f"Langkah {word}, {step_body}")
        else:
            lines.append(line)

    full_text = "\n\n".join(lines)
    full_text = strip_markdown_formatting(full_text)
    if not is_ref:
        full_text = strip_citations(full_text)

    return full_text


def get_audio_duration_seconds(file_path: Path) -> int:
    """Mengukur durasi audio dalam integer detik positif."""
    if not file_path.exists() or file_path.stat().st_size == 0:
        return 0

    if HAS_MUTAGEN:
        try:
            audio = MP3(str(file_path))
            if audio.info and audio.info.length > 0:
                return max(1, int(round(audio.info.length)))
        except Exception:
            pass

    # Fallback ke ffprobe
    try:
        cmd = [
            "ffprobe", "-v", "error",
            "-show_entries", "format=duration",
            "-of", "default=noprint_wrappers=1:nokey=1",
            str(file_path)
        ]
        res = subprocess.run(cmd, capture_output=True, text=True, check=True)
        dur = float(res.stdout.strip())
        return max(1, int(round(dur)))
    except Exception as e:
        print(f"Peringatan: Gagal membaca durasi {file_path}: {e}")
        return 0


async def generate_audio(text: str, output_path: Path):
    import edge_tts
    output_path.parent.mkdir(parents=True, exist_ok=True)
    temp_path = output_path.with_suffix(".tmp.mp3")
    try:
        comm = edge_tts.Communicate(text, voice=VOICE, rate=RATE)
        await comm.save(str(temp_path))
        temp_path.replace(output_path)
    except Exception as e:
        if temp_path.exists():
            temp_path.unlink()
        raise e


async def process_all():
    repo_root = Path(__file__).resolve().parent.parent.parent
    resources_dir = repo_root / "resources" / "buku-saku"
    audio_dir = repo_root / "public" / "audio" / "buku-saku"
    manifest_path = audio_dir / "manifest.json"
    hashes_path = audio_dir / ".speech_hashes.json"

    audio_dir.mkdir(parents=True, exist_ok=True)

    manifest = {}
    if manifest_path.exists():
        try:
            with open(manifest_path, "r", encoding="utf-8") as f:
                manifest = json.load(f)
        except Exception:
            manifest = {}

    speech_hashes = {}
    if hashes_path.exists():
        try:
            with open(hashes_path, "r", encoding="utf-8") as f:
                speech_hashes = json.load(f)
        except Exception:
            speech_hashes = {}

    # CLI args: bisa oper slug untuk dipaksa regenerasi (misal: "buku-saku-senam-dasar/00")
    cli_targets = set(sys.argv[1:]) if len(sys.argv) > 1 else set()

    books = [
        ("buku-saku-mandiri", resources_dir / "buku-saku-mandiri"),
        ("buku-saku-senam-dasar", resources_dir / "buku-saku-senam-dasar"),
    ]

    updated_manifest = dict(manifest)
    updated_hashes = dict(speech_hashes)
    total_processed = 0
    total_skipped = 0
    regenerated_files = []

    for book_slug, book_dir in books:
        if not book_dir.exists():
            print(f"Direktori materi tidak ditemukan: {book_dir}")
            continue

        md_files = sorted([f for f in book_dir.glob("*.md") if not f.name.startswith("._")])
        for md_file in md_files:
            prefix = md_file.name.split("-")[0]
            manifest_key = f"{book_slug}/{prefix}"
            rel_mp3_path = f"{book_slug}/{prefix}.mp3"
            mp3_path = audio_dir / rel_mp3_path

            content = md_file.read_text(encoding="utf-8")
            speech_text = markdown_to_speech_text(content)
            current_hash = hashlib.sha256(speech_text.encode("utf-8")).hexdigest()

            # Cek kondisi skip:
            # MP3 harus ada, mtime MP3 > mtime MD, durasi valid positif, ada di manifest,
            # DAN naskah suara (hash) tidak berubah sejak pembuatan terakhir.
            skip = False
            force = (manifest_key in cli_targets) or ("--force" in cli_targets)
            hash_changed = hashes_path.exists() and (speech_hashes.get(manifest_key) != current_hash)

            if not force and not hash_changed:
                if mp3_path.exists() and mp3_path.stat().st_size > 0:
                    mp3_mtime = mp3_path.stat().st_mtime
                    md_mtime = md_file.stat().st_mtime
                    if mp3_mtime > md_mtime or (hashes_path.exists() and speech_hashes.get(manifest_key) == current_hash):
                        existing_entry = manifest.get(manifest_key)
                        if existing_entry and existing_entry.get("durasi_detik", 0) > 0:
                            dur = get_audio_duration_seconds(mp3_path)
                            cps = len(speech_text) / dur if dur > 0 else 0
                            if dur > 0 and cps <= 20.0:
                                updated_manifest[manifest_key] = {
                                    "durasi_detik": dur,
                                    "berkas": rel_mp3_path
                                }
                                updated_hashes[manifest_key] = current_hash
                                skip = True
                                total_skipped += 1

            if skip:
                print(f"[SKIP] {manifest_key} (audio sudah mutakhir, durasi: {updated_manifest[manifest_key]['durasi_detik']}s)")
                continue

            reason = "target CLI" if force else ("naskah suara berubah" if hash_changed else "file baru/belum ada")
            print(f"[TTS] Membuat audio untuk {manifest_key} ({md_file.name}) [{reason}]...")
            retry_count = 3
            for attempt in range(1, retry_count + 1):
                try:
                    await generate_audio(speech_text, mp3_path)
                    break
                except Exception as e:
                    print(f"  Percobaan {attempt} gagal untuk {manifest_key}: {e}")
                    if attempt == retry_count:
                        print(f"ERROR: Gagal menghasilkan audio untuk {manifest_key}. Pertimbangkan beralih ke Gemini TTS.")
                        raise e
                    await asyncio.sleep(2)

            duration = get_audio_duration_seconds(mp3_path)
            # Validasi integritas audio: kecepatan baca normal adalah 8-15 karakter per detik.
            # Jika cps > 20, kemungkinan besar koneksi websocket terputus di tengah jalan.
            cps = len(speech_text) / duration if duration > 0 else 0
            if duration <= 0 or cps > 20.0:
                print(f"  Peringatan: Audio terpotong untuk {manifest_key} (durasi: {duration}s, cps: {cps:.1f}). Mengulangi...")
                for attempt in range(1, 4):
                    await generate_audio(speech_text, mp3_path)
                    duration = get_audio_duration_seconds(mp3_path)
                    cps = len(speech_text) / duration if duration > 0 else 0
                    if duration > 0 and cps <= 20.0:
                        break
                    await asyncio.sleep(2)

            if duration <= 0 or (len(speech_text) / duration) > 20.0:
                raise ValueError(f"Durasi audio tidak valid atau terpotong untuk {mp3_path}: {duration}s (cps: {cps:.1f})")

            updated_manifest[manifest_key] = {
                "durasi_detik": duration,
                "berkas": rel_mp3_path
            }
            updated_hashes[manifest_key] = current_hash
            total_processed += 1
            regenerated_files.append(rel_mp3_path)
            print(f"  ✓ Selesai: {manifest_key} -> {rel_mp3_path} ({duration} detik)")

    # Simpan manifest.json dengan urutan kunci terurut
    sorted_manifest = {k: updated_manifest[k] for k in sorted(updated_manifest.keys())}
    with open(manifest_path, "w", encoding="utf-8") as f:
        json.dump(sorted_manifest, f, indent=2, ensure_ascii=False)
        f.write("\n")

    # Simpan .speech_hashes.json
    with open(hashes_path, "w", encoding="utf-8") as f:
        json.dump(updated_hashes, f, indent=2, ensure_ascii=False)
        f.write("\n")

    print(f"\nProses TTS tuntas: {total_processed} dibuat, {total_skipped} dilewati.")
    print(f"Manifest disimpan di: {manifest_path}")
    if regenerated_files:
        print("Daftar MP3 yang dibuat ulang:")
        for rf in regenerated_files:
            print(f" - {rf}")


def main():
    asyncio.run(process_all())


if __name__ == "__main__":
    main()
