import json
import os
import re
import unittest
from pathlib import Path


class TestBukuSakuMaterials(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.repo_root = Path(__file__).resolve().parent.parent.parent
        cls.resources_dir = cls.repo_root / "resources" / "buku-saku"
        cls.audio_dir = cls.repo_root / "public" / "audio" / "buku-saku"
        cls.mandiri_dir = cls.resources_dir / "buku-saku-mandiri"
        cls.senam_dir = cls.resources_dir / "buku-saku-senam-dasar"
        cls.manifest_path = cls.audio_dir / "manifest.json"

    def _parse_front_matter(self, content: str):
        match = re.match(r"^---\r?\n(.*?)\r?\n---\r?\n(.*)$", content, re.DOTALL)
        if not match:
            return None, None
        raw_meta, body = match.groups()
        meta = {}
        for line in raw_meta.splitlines():
            line = line.strip()
            if not line or line.startswith("#"):
                continue
            if ":" not in line:
                return None, None
            key, val = line.split(":", 1)
            key = key.strip()
            val = val.strip()
            # Val can be integer
            if val.isdigit():
                meta[key] = int(val)
            else:
                meta[key] = val
        return meta, body

    def test_directories_exist(self):
        self.assertTrue(self.mandiri_dir.exists(), f"{self.mandiri_dir} does not exist")
        self.assertTrue(self.senam_dir.exists(), f"{self.senam_dir} does not exist")
        self.assertTrue(self.audio_dir.exists(), f"{self.audio_dir} does not exist")

    def test_mandiri_files_structure_and_count(self):
        self.assertTrue(self.mandiri_dir.exists())
        files = sorted([f for f in self.mandiri_dir.glob("*.md") if not f.name.startswith("._")])
        self.assertGreaterEqual(len(files), 16, f"Expected 16 files (00 + 15 chapters), got {len(files)}")
        
        # Check 00-pengantar.md
        pengantar = self.mandiri_dir / "00-pengantar.md"
        self.assertTrue(pengantar.exists(), "00-pengantar.md must exist in Mandiri")

        # Check 15 chapters
        chapter_files = [f for f in files if f.name != "00-pengantar.md" and f.name != "99-referensi.md"]
        self.assertEqual(len(chapter_files), 15, f"Expected exactly 15 chapter files in Mandiri, got {len(chapter_files)}")

        prefixes = [f.name.split("-")[0] for f in chapter_files]
        expected_prefixes = [f"{i:02d}" for i in range(1, 16)]
        self.assertEqual(prefixes, expected_prefixes, f"Prefixes mismatch: {prefixes} != {expected_prefixes}")

    def test_senam_dasar_files_structure_and_count(self):
        self.assertTrue(self.senam_dir.exists())
        files = sorted([f for f in self.senam_dir.glob("*.md") if not f.name.startswith("._")])
        self.assertGreaterEqual(len(files), 14, f"Expected 14 files (00 + 12 movements + 99), got {len(files)}")

        pengantar = self.senam_dir / "00-pengantar.md"
        self.assertTrue(pengantar.exists(), "00-pengantar.md must exist in Senam Dasar")

        referensi = self.senam_dir / "99-referensi.md"
        self.assertTrue(referensi.exists(), "99-referensi.md must exist in Senam Dasar")

        movement_files = [f for f in files if f.name not in ("00-pengantar.md", "99-referensi.md")]
        self.assertEqual(len(movement_files), 12, f"Expected exactly 12 movement files in Senam Dasar, got {len(movement_files)}")

        prefixes = [f.name.split("-")[0] for f in movement_files]
        expected_prefixes = [f"{i:02d}" for i in range(1, 13)]
        self.assertEqual(prefixes, expected_prefixes, f"Prefixes mismatch: {prefixes} != {expected_prefixes}")

    def test_markdown_front_matter_and_content(self):
        for book_dir, book_slug in [(self.mandiri_dir, "buku-saku-mandiri"), (self.senam_dir, "buku-saku-senam-dasar")]:
            if not book_dir.exists():
                continue
            files = sorted([f for f in book_dir.glob("*.md") if not f.name.startswith("._")])
            seen_prefixes = set()
            for file_path in files:
                prefix = file_path.name.split("-")[0]
                self.assertNotIn(prefix, seen_prefixes, f"Duplicate prefix {prefix} in {book_slug}")
                seen_prefixes.add(prefix)

                content = file_path.read_text(encoding="utf-8")
                meta, body = self._parse_front_matter(content)
                self.assertIsNotNone(meta, f"Invalid or missing front matter in {file_path.name}")
                self.assertIsNotNone(body, f"Invalid body in {file_path.name}")
                self.assertGreater(len(body.strip()), 0, f"Body cannot be empty in {file_path.name}")

                # Verify required front matter keys: exactly judul, bagian, urutan
                self.assertEqual(set(meta.keys()), {"judul", "bagian", "urutan"}, f"Front matter keys mismatch in {file_path.name}")
                self.assertIsInstance(meta["judul"], str)
                self.assertTrue(meta["judul"].strip(), f"judul empty in {file_path.name}")
                self.assertNotIn("\n", meta["judul"], f"judul must be single-line in {file_path.name}")

                self.assertIsInstance(meta["bagian"], str)
                self.assertTrue(meta["bagian"].strip(), f"bagian empty in {file_path.name}")
                self.assertNotIn("\n", meta["bagian"], f"bagian must be single-line in {file_path.name}")

                self.assertIsInstance(meta["urutan"], int)
                self.assertEqual(meta["urutan"], int(prefix), f"urutan {meta['urutan']} does not match prefix {prefix} in {file_path.name}")

                # Heading checks: body must not contain # H1
                lines = body.splitlines()
                for line in lines:
                    stripped = line.strip()
                    if stripped.startswith("#"):
                        self.assertTrue(stripped.startswith("##"), f"H1 (# ...) found in body of {file_path.name}: {line}")

                # Check no "Page x of y" footer
                self.assertNotRegex(body, r"Page\s+\d+\s+of\s+\d+", f"'Page x of y' footer found in {file_path.name}")

                # Check terminology replacements
                if book_slug == "buku-saku-mandiri":
                    self.assertNotIn("## Patokan indra", body, f"'## Patokan indra' must be replaced with '## Cara tahu sudah benar' in {file_path.name}")
                    if prefix not in ("00", "99"):
                        self.assertIn("## Cara tahu sudah benar", body, f"'## Cara tahu sudah benar' missing in {file_path.name}")
                elif book_slug == "buku-saku-senam-dasar":
                    self.assertNotIn("## Patokan rasa dan napas", body, f"'## Patokan rasa dan napas' must be replaced with '## Yang kamu rasakan' in {file_path.name}")

                # Steps check for numbered chapters
                if prefix not in ("00", "99"):
                    self.assertIn("## Langkah-langkah", body, f"Missing '## Langkah-langkah' heading in {file_path.name}")
                    self.assertRegex(body, r"(?m)^1\.\s+", f"Missing numbered step 1. in {file_path.name}")

    def test_audio_and_manifest(self):
        self.assertTrue(self.manifest_path.exists(), f"manifest.json does not exist at {self.manifest_path}")
        with open(self.manifest_path, "r", encoding="utf-8") as f:
            manifest = json.load(f)

        self.assertIsInstance(manifest, dict)
        
        # Manifest keys must match "<slug-buku>/<NN>"
        # Mandiri: 00..15 = 16 files
        # Senam: 00..12 + 99 = 14 files
        expected_keys = []
        for i in range(16):
            expected_keys.append(f"buku-saku-mandiri/{i:02d}")
        for i in range(13):
            expected_keys.append(f"buku-saku-senam-dasar/{i:02d}")
        expected_keys.append("buku-saku-senam-dasar/99")

        self.assertEqual(set(manifest.keys()), set(expected_keys), "Manifest keys do not match expected book/chapter list")

        for key, entry in manifest.items():
            self.assertIn("durasi_detik", entry, f"Missing durasi_detik in manifest[{key}]")
            self.assertIn("berkas", entry, f"Missing berkas in manifest[{key}]")
            self.assertIsInstance(entry["durasi_detik"], int, f"durasi_detik must be int in manifest[{key}]")
            self.assertGreater(entry["durasi_detik"], 0, f"durasi_detik must be positive in manifest[{key}]")
            
            mp3_path = self.audio_dir / entry["berkas"]
            self.assertTrue(mp3_path.exists(), f"Audio file does not exist: {mp3_path}")
            self.assertGreater(mp3_path.stat().st_size, 0, f"Audio file is empty: {mp3_path}")

    def test_speech_text_rules(self):
        # 1. Naskah suara tidak boleh memuat kutipan sumber dalam tanda kurung
        # 2. Naskah suara 99-referensi tidak boleh memuat URL (cukup "Nomor satu. <nama sumber>")
        # 3. Naskah suara tidak boleh memuat penanda markdown (**, _, backtick)
        import sys
        scripts_dir = str(self.repo_root)
        if scripts_dir not in sys.path:
            sys.path.insert(0, scripts_dir)
        from scripts.buku_saku.tts import markdown_to_speech_text

        citation_regex = re.compile(r"\([^)]*(?:AFB|RNIB|NDVS|Battle for Blindness|Blind Welfare Society|Kemenkes|\b\d{4}[a-z]?\b)[^)]*\)")

        for book_dir, book_slug in [(self.mandiri_dir, "buku-saku-mandiri"), (self.senam_dir, "buku-saku-senam-dasar")]:
            files = sorted([f for f in book_dir.glob("*.md") if not f.name.startswith("._")])
            for file_path in files:
                content = file_path.read_text(encoding="utf-8")
                speech = markdown_to_speech_text(content)

                # Naskah suara tidak boleh memuat penanda markdown
                self.assertNotIn("**", speech, f"Found ** in speech text of {file_path.name}")
                self.assertNotIn("`", speech, f"Found backtick in speech text of {file_path.name}")

                # Bab materi tidak boleh memuat kutipan sumber
                if file_path.name != "99-referensi.md":
                    match = citation_regex.search(speech)
                    self.assertIsNone(match, f"Found source citation in speech text of {file_path.name}: {match.group(0) if match else ''}")

                # 99-referensi tidak boleh memuat URL
                if file_path.name == "99-referensi.md":
                    self.assertNotIn("http://", speech, f"Found http:// in speech text of {file_path.name}")
                    self.assertNotIn("https://", speech, f"Found https:// in speech text of {file_path.name}")
                    self.assertRegex(speech, r"Nomor satu\.\s+", f"Expected 'Nomor satu.' format in speech text of {file_path.name}")


if __name__ == "__main__":
    unittest.main()
