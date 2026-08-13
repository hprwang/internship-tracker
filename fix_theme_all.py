#!/usr/bin/env python3
"""Fix ALL green theme references to violet across entire project"""
import re
import os

def get_php_files():
    """Get all PHP, JS, CSS files in the project"""
    files = []
    for root, dirs, filenames in os.walk('.'):
        dirs[:] = [d for d in dirs if d not in ['.git', 'node_modules', 'vendor', 'sql', 'tests']]
        for f in filenames:
            if f.endswith(('.php', '.js', '.css')):
                files.append(os.path.join(root, f))
    return files

def get_replacements():
    """Comprehensive replacement patterns"""
    return [
        (r'--green-neon:\s*#22C55E', '--primary: #7C3AED'),
        (r'--green-emerald:\s*#16A34A', '--primary-hover: #6D28D9'),
        (r'--green-glow:\s*#4ADE80', '--primary-dim: #8B5CF6'),
        (r'--green-muted:\s*#86EFAC', '--primary-muted: #A78BFA'),
        (r'var\(--green-neon\)', 'var(--primary)'),
        (r'var\(--green-emerald\)', 'var(--primary-hover)'),
        (r'var\(--green-glow\)', 'var(--primary-dim)'),
        (r'var\(--green-muted\)', 'var(--primary-muted)'),
        (r'#22C55E\b', '#7C3AED'),
        (r'#22c55e\b', '#7C3AED'),
        (r'#16A34A\b', '#6D28D9'),
        (r'#4ADE80\b', '#8B5CF6'),
        (r'#86EFAC\b', '#A78BFA'),
        (r'rgba\(34,\s*197,\s*94,', 'rgba(124, 58, 237,'),
    ]

def process_file(filepath):
    try:
        with open(filepath, 'r', encoding='utf-8', errors='ignore') as f:
            content = f.read()
        original = content
        for pattern, replacement in get_replacements():
            content = re.sub(pattern, replacement, content)
        if content != original:
            with open(filepath, 'w', encoding='utf-8') as f:
                f.write(content)
            return True
        return False
    except Exception as e:
        print(f"Error in {filepath}: {e}")
        return False

def main():
    files = get_php_files()
    print(f"Processing {len(files)} files...\n")
    changed = 0
    for f in files:
        if process_file(f):
            print(f"✓ {f}")
            changed += 1
    print(f"\n✓ Complete! Updated {changed} files.")

if __name__ == '__main__':
    main()