"""Exercise the real ZIP writer offline with synthetic payloads only."""
import os
from pathlib import Path
import subprocess
import sys
import tempfile
import unittest
import zipfile

REPO = Path(__file__).resolve().parents[2]


class ReleasePackageTest(unittest.TestCase):
    def test_zip_is_deterministic_and_preserves_executable_files(self):
        script = (REPO / 'deploy/azure/build.sh').read_text()
        writer = script.split("<<'PYZIP'\n", 1)[1].split('\nPYZIP', 1)[0]
        with tempfile.TemporaryDirectory(prefix='trackpro-zip-test-') as temporary:
            root = Path(temporary)
            (root / 'app').mkdir()
            (root / 'app/z.php').write_text('<?php // synthetic\n')
            executable = root / 'app/a.sh'
            executable.write_text('#!/bin/sh\nexit 0\n')
            executable.chmod(0o755)
            for name, timestamp in [('first.zip', 1700000000), ('second.zip', 1800000000)]:
                for path in (root / 'app').rglob('*'):
                    os.utime(path, (timestamp, timestamp))
                subprocess.run([sys.executable, '-', name, 'app'], input=writer,
                               text=True, cwd=root, check=True, capture_output=True)
            self.assertEqual((root / 'first.zip').read_bytes(), (root / 'second.zip').read_bytes())
            with zipfile.ZipFile(root / 'first.zip') as archive:
                self.assertIsNone(archive.testzip())
                self.assertEqual(archive.namelist(), ['app/', 'app/a.sh', 'app/z.php'])
                self.assertEqual(archive.getinfo('app/a.sh').external_attr >> 16 & 0o777, 0o755)
                self.assertTrue(all(info.date_time == (1980, 1, 1, 0, 0, 0) for info in archive.infolist()))
            # Exclusive creation must refuse replacing an existing artifact.
            result = subprocess.run([sys.executable, '-', 'first.zip', 'app'], input=writer,
                                    text=True, cwd=root, capture_output=True)
            self.assertNotEqual(result.returncode, 0)


if __name__ == '__main__':
    unittest.main()
