#!/usr/bin/env python3
import re
import sys

files = [
    'php/admin_students.php',
    'php/admin_companies.php',
    'php/admin_internships.php',
    'php/admin_applications.php',
    'php/admin_reports.php',
    'php/admin_settings.php',
    'profile.php',
    'browse_internships.php',
    'progress.php',
    'companies.php',
    'calendar.php'
]

replacements = {
    # CSS variables
    r'--green-neon: #22C55E': '--primary: #7C3AED',
    r'--green-emerald: #16A34A': '--primary-hover: #6D28D9',
    r'--green-glow: #4ADE80': '--primary-glow: #A78BFA',

    # Variable references
    r'var\(--green-neon\)': 'var(--primary)',
    r'var\(--green-emerald\)': 'var(--primary-hover)',
    r'var\(--green-glow\)': 'var(--primary-glow)',

    # Hex colors
    r'#22C55E': '#7C3AED',
    r'#22c55e': '#7C3AED',
    r'#16A34A': '#6D28D9',
    r'#4ADE80': '#A78BFA',

    # RGBA colors
    r'rgba\(34,197,94,': 'rgba(124,58,237,',
    r'rgba\(34, 197, 94,': 'rgba(124,58,237,',
}

for file in files:
    try:
        with open(file, 'r', encoding='utf-8') as f:
            content = f.read()

        original = content
        for pattern, replacement in replacements.items():
            content = re.sub(pattern, replacement, content)

        if content != original:
            with open(file, 'w', encoding='utf-8') as f:
                f.write(content)
            print(f'✓ Updated {file}')
        else:
            print(f'○ No changes needed in {file}')
    except Exception as e:
        print(f'✗ Error processing {file}: {e}')

print('\nTheme conversion complete!')
