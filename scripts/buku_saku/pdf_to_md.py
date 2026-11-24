#!/usr/bin/env python3
"""
Ekstraksi teks dari PDF Buku Saku ke Markdown bersih untuk Tuturnusa.
Mendukung:
1. Buku Saku Mandiri — Panduan Aktivitas Sehari-Hari Tunanetra.pdf
2. Buku Saku Senam Dasar — Panduan Olahraga Mandiri Tunanetra.pdf

Penggunaan:
    python3 scripts/buku_saku/pdf_to_md.py INPUT.pdf OUTPUT_DIR
"""

import os
import re
import subprocess
import sys
from pathlib import Path


def slugify(text: str) -> str:
    text = text.lower()
    text = re.sub(r"[^\w\s-]", "", text)
    text = re.sub(r"[\s_-]+", "-", text).strip("-")
    return text


def extract_raw_text(pdf_path: str) -> str:
    cmd = ["pdftotext", pdf_path, "-"]
    res = subprocess.run(cmd, capture_output=True, text=True, check=True)
    return res.stdout


def write_if_changed(file_path: Path, new_content: str):
    if file_path.exists():
        try:
            if file_path.read_text(encoding="utf-8") == new_content:
                return
        except Exception:
            pass
    file_path.write_text(new_content, encoding="utf-8")


def clean_page_artifacts(text: str) -> str:
    # Fix typos from PDF extraction
    text = text.replace("terburuburu", "terburu-buru")
    # Remove form feeds
    text = text.replace("\x0c", "\n")
    # Remove running headers and page numbers
    text = re.sub(r"Buku Saku Mandiri — Panduan Aktivitas Sehari-Hari Tunanetra\s*\n\s*Page \d+ of \d+\s*\n?", "", text)
    text = re.sub(r"Buku Saku Senam Dasar — Panduan Olahraga Mandiri Tunanetra\s*\n\s*Page \d+ of \d+\s*\n?", "", text)
    text = re.sub(r"Page \d+ of \d+", "", text)
    return text


def process_mandiri(raw_text: str, output_dir: Path):
    output_dir.mkdir(parents=True, exist_ok=True)
    cleaned = clean_page_artifacts(raw_text)

    # 1. Extract 00-pengantar.md
    # Contains:
    # BUKU SAKU AKTIVITAS SEHARI-HARI ... up to "Bagian 1: Kebersihan dan Perawatan Diri"
    intro_match = re.search(
        r"BUKU SAKU AKTIVITAS SEHARI-HARI.*?(?=Bagian 1: Kebersihan dan Perawatan Diri)",
        cleaned,
        re.DOTALL
    )
    if intro_match:
        intro_content = intro_match.group(0).strip()
        # Parse sections
        # Cara Menggunakan Buku Ini
        # Aturan Emas Kemandirian
        # Prinsip Dasar Sebelum Memulai
        cara_match = re.search(r"Cara Menggunakan Buku Ini:\s*(.*?)(?=Aturan Emas Kemandirian:)", intro_content, re.DOTALL)
        aturan_match = re.search(r"Aturan Emas Kemandirian:\s*(.*?)(?=Prinsip Dasar Sebelum Memulai)", intro_content, re.DOTALL)
        prinsip_match = re.search(r"Prinsip Dasar Sebelum Memulai\s*\n(.*?)(?=Metode Arah Jam)", intro_content, re.DOTALL)
        
        # 4 Prinsip Dasar:
        metode_jam = re.search(r"Metode Arah Jam\s*\n\s*(.*?)(?=Penanda Taktil)", intro_content, re.DOTALL)
        penanda_taktil = re.search(r"Penanda Taktil\s*\n\s*(.*?)(?=Indra Pengganti)", intro_content, re.DOTALL)
        indra_pengganti = re.search(r"Indra Pengganti\s*\n\s*(.*?)(?=Konsistensi Posisi)", intro_content, re.DOTALL)
        konsistensi_posisi = re.search(r"Konsistensi Posisi\s*\n\s*(.*?)$", intro_content, re.DOTALL)

        pengantar_md = [
            "---",
            "judul: Pengantar Buku Saku Mandiri",
            "bagian: Pengantar",
            "urutan: 0",
            "---",
            "",
            "## Tentang Buku Ini",
            "",
            "Kumpulan 15 panduan praktis kegiatan harian berbasis rabaan, pendengaran, dan posisi tetap—disusun ringkas agar nyaman didengarkan langsung melalui pembaca suara otomatis.",
            "",
            "## Cara Menggunakan Buku Ini",
            "",
            re.sub(r"\s+", " ", cara_match.group(1)).strip() if cara_match else "",
            "",
            "## Aturan Emas Kemandirian",
            "",
            re.sub(r"\s+", " ", aturan_match.group(1)).strip() if aturan_match else "",
            "",
            "## Prinsip Dasar Sebelum Memulai",
            "",
            re.sub(r"\s+", " ", prinsip_match.group(1).replace("Prinsip Dasar", "").replace("Penerapan dalam Kegiatan Sehari-Hari", "")).strip() if prinsip_match else "",
            "",
            "### Metode Arah Jam",
            "",
            re.sub(r"\s+", " ", metode_jam.group(1)).strip() if metode_jam else "",
            "",
            "### Penanda Taktil",
            "",
            re.sub(r"\s+", " ", penanda_taktil.group(1)).strip() if penanda_taktil else "",
            "",
            "### Indra Pengganti",
            "",
            re.sub(r"\s+", " ", indra_pengganti.group(1)).strip() if indra_pengganti else "",
            "",
            "### Konsistensi Posisi",
            "",
            re.sub(r"\s+", " ", konsistensi_posisi.group(1)).strip() if konsistensi_posisi else "",
            ""
        ]
        write_if_changed(output_dir / "00-pengantar.md", "\n".join(pengantar_md))

    # 2. Extract chapters 1 to 15
    # Mapping chapter number to Bagian
    sections = [
        (1, 4, "Bagian 1: Kebersihan dan Perawatan Diri"),
        (5, 8, "Bagian 2: Makan, Minum, dan Dapur Dasar"),
        (9, 11, "Bagian 3: Berpakaian dan Kerapian Pribadi"),
        (12, 15, "Bagian 4: Transaksi, Teknologi, dan Mobilitas Ringan"),
    ]
    def get_section_for_chap(num: int) -> str:
        for start, end, name in sections:
            if start <= num <= end:
                return name
        return "Umum"

    # Split chapters
    # Each chapter starts with "Bab X. Title"
    chapter_pattern = r"(Bab\s+(\d+)\.\s+([^\n]+))\s*\n(.*?)(?=(?:Bab\s+\d+\.|Indeks Cepat|$))"
    matches = list(re.finditer(chapter_pattern, cleaned, re.DOTALL))

    for m in matches:
        chap_num = int(m.group(2))
        raw_title = m.group(3).strip()
        body = m.group(4).strip()
        sec_name = get_section_for_chap(chap_num)

        # Parse Alat dan bahan
        alat_match = re.search(r"Alat dan bahan:\s*([^\n]+(?:\n(?!\d+\.)[^\n]+)*)", body)
        alat_text = ""
        if alat_match:
            alat_text = re.sub(r"\s+", " ", alat_match.group(1)).strip()

        # Parse numbered steps
        steps_match = re.search(r"(\d+\.\s+.*?)(?=Patokan indra:|$)", body, re.DOTALL)
        steps_text = ""
        if steps_match:
            raw_steps = steps_match.group(1).strip()
            # Clean up linebreaks within each step
            step_items = re.findall(r"(\d+)\.\s+(.*?)(?=(?:\n\d+\.|\Z))", raw_steps, re.DOTALL)
            formatted_steps = []
            for s_num, s_text in step_items:
                clean_s = re.sub(r"\s+", " ", s_text).strip()
                formatted_steps.append(f"{s_num}. {clean_s}")
            steps_text = "\n".join(formatted_steps)

        # Parse Patokan indra -> "Cara tahu sudah benar"
        patokan_match = re.search(r"Patokan indra:\s*(.*?)(?=(?:Bagian \d+:|Indeks Cepat|$))", body, re.DOTALL)
        patokan_text = ""
        if patokan_match:
            patokan_text = re.sub(r"\s+", " ", patokan_match.group(1)).strip()

        # Build Markdown
        # Filename: NN-<slug-title>.md
        file_slug = f"{chap_num:02d}-{slugify(raw_title)}"
        file_path = output_dir / f"{file_slug}.md"

        full_title = f"Bab {chap_num}. {raw_title}"

        lines = [
            "---",
            f"judul: {full_title}",
            f"bagian: {sec_name}",
            f"urutan: {chap_num}",
            "---",
            "",
            "## Alat dan bahan",
            "",
            alat_text,
            "",
            "## Langkah-langkah",
            "",
            steps_text,
            "",
            "## Cara tahu sudah benar",
            "",
            patokan_text,
            ""
        ]
        write_if_changed(file_path, "\n".join(lines))


def process_senam_dasar(raw_text: str, output_dir: Path):
    output_dir.mkdir(parents=True, exist_ok=True)
    cleaned = clean_page_artifacts(raw_text)

    # 1. Extract 00-pengantar.md
    intro_match = re.search(
        r"BUKU SAKU SENAM DAN KEBUGARAN DASAR.*?(?=Bagian 1: Pemanasan dan Kelenturan Tubuh Atas)",
        cleaned,
        re.DOTALL
    )
    if intro_match:
        intro_content = intro_match.group(0).strip()
        prinsip_match = re.search(r"Prinsip Panduan Audio:\s*(.*?)(?=Peringatan Keselamatan:)", intro_content, re.DOTALL)
        peringatan_match = re.search(r"Peringatan Keselamatan:\s*(.*?)(?=Persiapan Ruang Aman Sebelum Senam)", intro_content, re.DOTALL)
        persiapan_intro = re.search(r"Persiapan Ruang Aman Sebelum Senam\s*\n(.*?)(?=Uji Jangkauan Ruang)", intro_content, re.DOTALL)

        # 4 Langkah Persiapan
        uji_ruang = re.search(r"Uji Jangkauan Ruang\s*\n\s*(.*?)(?=Cek Alas Lantai)", intro_content, re.DOTALL)
        cek_lantai = re.search(r"Cek Alas Lantai\s*\n\s*(.*?)(?=Siapkan Penopang Tetap)", intro_content, re.DOTALL)
        siapkan_penopang = re.search(r"Siapkan Penopang Tetap\s*\n\s*(.*?)(?=Pakaian dan Minum)", intro_content, re.DOTALL)
        pakaian_minum = re.search(r"Pakaian dan Minum\s*\n\s*(.*?)$", intro_content, re.DOTALL)

        clean_persiapan = re.sub(
            r"\s+", " ",
            persiapan_intro.group(1).replace("Langkah Persiapan", "").replace("Cara Memastikan Keamanan di Rumah", "")
        ).strip() if persiapan_intro else ""

        pengantar_md = [
            "---",
            "judul: Pengantar Buku Saku Senam Dasar",
            "bagian: Pengantar",
            "urutan: 0",
            "---",
            "",
            "## Tentang Buku Ini",
            "",
            "Rangkaian 12 gerakan senam dasar di rumah—meliputi pemanasan, kardio ringan, penguatan otot, keseimbangan, dan pendinginan—dengan instruksi verbal jelas berbasis rabaan tanpa petunjuk visual.",
            "",
            "## Prinsip Panduan Audio",
            "",
            re.sub(r"\s+", " ", prinsip_match.group(1)).strip() if prinsip_match else "",
            "",
            "## Peringatan Keselamatan",
            "",
            re.sub(r"\s+", " ", peringatan_match.group(1)).strip() if peringatan_match else "",
            "",
            "## Persiapan Ruang Aman Sebelum Senam",
            "",
            clean_persiapan,
            "",
            "### Uji Jangkauan Ruang",
            "",
            re.sub(r"\s+", " ", uji_ruang.group(1)).strip() if uji_ruang else "",
            "",
            "### Cek Alas Lantai",
            "",
            re.sub(r"\s+", " ", cek_lantai.group(1)).strip() if cek_lantai else "",
            "",
            "### Siapkan Penopang Tetap",
            "",
            re.sub(r"\s+", " ", siapkan_penopang.group(1)).strip() if siapkan_penopang else "",
            "",
            "### Pakaian dan Minum",
            "",
            re.sub(r"\s+", " ", pakaian_minum.group(1)).strip() if pakaian_minum else "",
            ""
        ]
        write_if_changed(output_dir / "00-pengantar.md", "\n".join(pengantar_md))

    # 2. Extract movements 1 to 12
    sections = [
        (1, 3, "Bagian 1: Pemanasan dan Kelenturan Tubuh Atas"),
        (4, 6, "Bagian 2: Kardio Ringan di Tempat"),
        (7, 9, "Bagian 3: Latihan Kekuatan Dasar dengan Penopang"),
        (10, 12, "Bagian 4: Keseimbangan dan Pendinginan Pernapasan"),
    ]
    def get_section_for_mov(num: int) -> str:
        for start, end, name in sections:
            if start <= num <= end:
                return name
        return "Umum"

    movement_pattern = r"(Gerakan\s+(\d+)\.\s+([^\n]+))\s*\n(.*?)(?=(?:Gerakan\s+\d+\.|Rangkaian Program|Daftar Referensi|$))"
    matches = list(re.finditer(movement_pattern, cleaned, re.DOTALL))

    for m in matches:
        mov_num = int(m.group(2))
        raw_title = m.group(3).strip()
        body = m.group(4).strip()
        sec_name = get_section_for_mov(mov_num)

        # Parse Posisi dan alat:
        posisi_match = re.search(r"Posisi dan alat:\s*([^\n]+(?:\n(?!\d+\.)[^\n]+)*)", body)
        posisi_text = ""
        if posisi_match:
            posisi_text = re.sub(r"\s+", " ", posisi_match.group(1)).strip()

        # Parse numbered steps
        steps_match = re.search(r"(\d+\.\s+.*?)(?=Patokan rasa dan napas:|$)", body, re.DOTALL)
        steps_text = ""
        if steps_match:
            raw_steps = steps_match.group(1).strip()
            step_items = re.findall(r"(\d+)\.\s+(.*?)(?=(?:\n\d+\.|\Z))", raw_steps, re.DOTALL)
            formatted_steps = []
            for s_num, s_text in step_items:
                clean_s = re.sub(r"\s+", " ", s_text).strip()
                formatted_steps.append(f"{s_num}. {clean_s}")
            steps_text = "\n".join(formatted_steps)

        # Parse Patokan rasa dan napas -> "Yang kamu rasakan"
        patokan_match = re.search(r"Patokan rasa dan napas:\s*(.*?)(?=(?:Bagian \d+:|Rangkaian Program|Daftar Referensi|$))", body, re.DOTALL)
        patokan_text = ""
        if patokan_match:
            patokan_text = re.sub(r"\s+", " ", patokan_match.group(1)).strip()

        file_slug = f"{mov_num:02d}-{slugify(raw_title)}"
        file_path = output_dir / f"{file_slug}.md"
        full_title = f"Gerakan {mov_num}. {raw_title}"

        lines = [
            "---",
            f"judul: {full_title}",
            f"bagian: {sec_name}",
            f"urutan: {mov_num}",
            "---",
            "",
            "## Posisi dan alat",
            "",
            posisi_text,
            "",
            "## Langkah-langkah",
            "",
            steps_text,
            "",
            "## Yang kamu rasakan",
            "",
            patokan_text,
            ""
        ]
        write_if_changed(file_path, "\n".join(lines))

    # 3. Extract 99-referensi.md
    ref_match = re.search(r"Daftar Referensi\s*\n(.*)$", cleaned, re.DOTALL)
    if ref_match:
        raw_refs = ref_match.group(1).strip()
        ref_items = re.findall(r"(\d+)\.\s+(.*?)(?=(?:\n\d+\.|\Z))", raw_refs, re.DOTALL)
        formatted_refs = []
        for r_num, r_text in ref_items:
            clean_r = re.sub(r"\s+", " ", r_text).strip()
            # Repair URLs broken across line breaks in PDF
            clean_r = re.sub(r"https://battleforblindnes\s*s\.org", "https://battleforblindness.org", clean_r)
            clean_r = re.sub(r"https://blindwelfaresoci\s*ety\.in", "https://blindwelfaresociety.in", clean_r)
            clean_r = re.sub(r"view_arti\s*kel", "view_artikel", clean_r)
            clean_r = re.sub(r"kemkes\.go\.id/i\s*d", "kemkes.go.id/id", clean_r)
            clean_r = re.sub(r"ndvisionservices\.co\s*m", "ndvisionservices.com", clean_r)
            clean_r = re.sub(r"rnib\.org\.u\s*k", "rnib.org.uk", clean_r)
            formatted_refs.append(f"{r_num}. {clean_r}")
        
        ref_lines = [
            "---",
            "judul: Daftar Referensi",
            "bagian: Referensi",
            "urutan: 99",
            "---",
            "",
            "## Referensi",
            "",
            "\n".join(formatted_refs),
            ""
        ]
        write_if_changed(output_dir / "99-referensi.md", "\n".join(ref_lines))


def main():
    if len(sys.argv) < 3:
        print("Penggunaan: python3 pdf_to_md.py INPUT.pdf OUTPUT_DIR")
        sys.exit(1)

    pdf_path = sys.argv[1]
    output_dir = Path(sys.argv[2])

    if not os.path.exists(pdf_path):
        print(f"Error: berkas PDF tidak ditemukan: {pdf_path}")
        sys.exit(1)

    raw_text = extract_raw_text(pdf_path)

    if "Senam" in pdf_path or "SENAM DAN KEBUGARAN DASAR" in raw_text:
        print(f"Memproses Buku Saku Senam Dasar: {pdf_path} -> {output_dir}")
        process_senam_dasar(raw_text, output_dir)
    elif "Aktivitas Sehari-Hari" in pdf_path or "BUKU SAKU AKTIVITAS SEHARI-HARI" in raw_text or "mandiri" in str(output_dir).lower():
        print(f"Memproses Buku Saku Mandiri: {pdf_path} -> {output_dir}")
        process_mandiri(raw_text, output_dir)
    else:
        print(f"Error: Format PDF tidak dikenali: {pdf_path}")
        sys.exit(1)

    print("Ekstraksi selesai dengan sukses.")


if __name__ == "__main__":
    main()
