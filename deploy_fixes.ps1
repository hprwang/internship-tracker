# Deploy updated files to the live XAMPP site.
# Run from a normal PowerShell window:  & "C:\Users\mukhi\Documents\Codex\2026-08-06\ca-2\internship-tracker\deploy_fixes.ps1"
$src = "C:\Users\mukhi\Documents\Codex\2026-08-06\ca-2\internship-tracker"
$dst = "C:\xampp\htdocs\internship-tracker"

# Core auth fixes (company login / dashboard access)
Copy-Item "$src\php\auth.php" "$dst\php\auth.php" -Force
Copy-Item "$src\php\config.php" "$dst\php\config.php" -Force

# Student "Browse Internships" feature
Copy-Item "$src\php\internships.php" "$dst\php\internships.php" -Force
Copy-Item "$src\php\company_applications.php" "$dst\php\company_applications.php" -Force
Copy-Item "$src\browse_internships.php" "$dst\browse_internships.php" -Force

# Remove the old student Internships page (replaced by Browse Internships)
Remove-Item "$dst\internships.php" -Force -ErrorAction SilentlyContinue
Remove-Item "$dst\internship-details.php" -Force -ErrorAction SilentlyContinue

# Nav links to Browse Internships across student pages
Copy-Item "$src\dashboard.php" "$dst\dashboard.php" -Force
Copy-Item "$src\companies.php" "$dst\companies.php" -Force
Copy-Item "$src\progress.php" "$dst\progress.php" -Force
Copy-Item "$src\profile.php" "$dst\profile.php" -Force
Copy-Item "$src\change_password.php" "$dst\change_password.php" -Force

# Admin: company portal companies + internship posts on admin pages
Copy-Item "$src\php\admin.php" "$dst\php\admin.php" -Force
Copy-Item "$src\php\admin_companies.php" "$dst\php\admin_companies.php" -Force
Copy-Item "$src\php\admin_dashboard.php" "$dst\php\admin_dashboard.php" -Force
Copy-Item "$src\php\admin_internships.php" "$dst\php\admin_internships.php" -Force
Write-Host "Deployed all updated files to $dst"