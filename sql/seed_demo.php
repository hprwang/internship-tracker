<?php
/**
 * InternTrack — Demo Seed Script (CLI only)
 *
 * Populates the unified `internship_tracker1` database with deterministic,
 * realistic demo data so the app demos well on XAMPP:
 *
 *   - 6 companies, 4 demo students, 1 demo company account, 1 admin
 *   - 10 student internships spread across the last 6 months + next 2 months
 *   - Weekly progress logs for ongoing internships
 *   - 6 company job postings with applications in every status
 *   - Sample in-app notifications for every demo account
 *
 * Idempotent: safe to run any number of times. Demo-owned rows (student
 * internships, postings, notifications) are reset + re-inserted on every run;
 * shared rows (companies, users) are upserted by unique key.
 *
 * Demo accounts:
 *   student:  demo_student1..4@interntracker.com  /  Student@123
 *   company:  demo_company@interntracker.com      /  Company@123
 *   admin:    admin@interntracker.com             /  Admin@123
 *
 * Usage: php sql/seed_demo.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../php/config.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    die('This script may only be run from the command line.');
}

$db = Database::getConnection();

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/** Relative date, e.g. relDate('-2 months') -> 'YYYY-MM-DD' */
function relDate(string $expr): string
{
    $ts = strtotime($expr, strtotime('today'));
    return $ts === false ? date('Y-m-d') : date('Y-m-d', $ts);
}

/** Relative datetime, e.g. relDateTime('-3 days') -> 'YYYY-MM-DD HH:MM:SS' */
function relDateTime(string $expr): string
{
    $ts = strtotime($expr, strtotime('now'));
    return $ts === false ? date('Y-m-d H:i:s') : date('Y-m-d H:i:s', $ts);
}

/** Upsert a company by unique name and return its id. */
function upsertCompany(PDO $db, array $c): int
{
    $stmt = $db->prepare("
        INSERT INTO companies (name, industry, description, website, location, contact_person, contact_email, contact_phone, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active')
        ON DUPLICATE KEY UPDATE
          industry       = VALUES(industry),
          description    = VALUES(description),
          website        = VALUES(website),
          location       = VALUES(location),
          contact_person = VALUES(contact_person),
          contact_email  = VALUES(contact_email),
          contact_phone  = VALUES(contact_phone),
          id             = LAST_INSERT_ID(id)
    ");
    $stmt->execute([
        $c['name'], $c['industry'], $c['description'], $c['website'],
        $c['location'], $c['person'], $c['email'], $c['phone'],
    ]);
    return (int) $db->lastInsertId();
}

/** Upsert a user by unique username and return its id. */
function upsertUser(PDO $db, array $u): int
{
    $stmt = $db->prepare("
        INSERT INTO users (username, email, password_hash, role, full_name, company_id, is_active)
        VALUES (?, ?, ?, ?, ?, ?, 1)
        ON DUPLICATE KEY UPDATE
          email         = VALUES(email),
          password_hash = VALUES(password_hash),
          role          = VALUES(role),
          full_name     = VALUES(full_name),
          company_id    = VALUES(company_id),
          id            = LAST_INSERT_ID(id)
    ");
    $stmt->execute([$u['username'], $u['email'], $u['password_hash'], $u['role'], $u['full_name'], $u['company_id']]);
    return (int) $db->lastInsertId();
}

/** Insert a student internship row. */
function insertInternship(PDO $db, array $i): int
{
    $stmt = $db->prepare("
        INSERT INTO internships
          (student_id, company_id, title, description, start_date, end_date, status, stipend, work_mode, supervisor_name, supervisor_email, notes)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $i['student_id'], $i['company_id'], $i['title'], $i['description'],
        $i['start_date'], $i['end_date'], $i['status'], $i['stipend'],
        $i['work_mode'], $i['supervisor'], $i['supervisor_email'], $i['notes'],
    ]);
    return (int) $db->lastInsertId();
}

/** Insert a company job posting. */
function insertPosting(PDO $db, array $p): int
{
    $stmt = $db->prepare("
        INSERT INTO company_internships (company_id, title, description, requirements, location, duration, stipend, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $p['company_id'], $p['title'], $p['description'], $p['requirements'],
        $p['location'], $p['duration'], $p['stipend'], $p['status'],
    ]);
    return (int) $db->lastInsertId();
}

/** Insert an application to a posting. */
function insertApplication(PDO $db, array $a): void
{
    $stmt = $db->prepare("
        INSERT INTO applications (company_internship_id, student_id, cover_letter, notes, status, applied_at)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $a['posting_id'], $a['student_id'], $a['cover_letter'], $a['notes'] ?? null,
        $a['status'], $a['applied_at'],
    ]);
}

/** Insert an in-app notification. */
function insertNotification(PDO $db, int $userId, string $title, string $message, string $type, int $read, string $createdAt): void
{
    $stmt = $db->prepare("
        INSERT INTO notifications (user_id, title, message, type, channel, is_read, created_at)
        VALUES (?, ?, ?, ?, 'in_app', ?, ?)
    ");
    $stmt->execute([$userId, $title, $message, $type, $read, $createdAt]);
}

// ---------------------------------------------------------------------------
// 1. Companies (6)
// ---------------------------------------------------------------------------
$companies = [
    ['name' => 'TechNova Solutions', 'industry' => 'Information Technology',
     'description' => 'Software consultancy building web and mobile products for clients across Nepal and beyond.',
     'website' => 'https://technova.io', 'location' => 'Kathmandu, Nepal',
     'person' => 'Priya Sharma', 'email' => 'priya@technova.io', 'phone' => '+977-1-4455667'],
    ['name' => 'FinEdge Corp', 'industry' => 'Finance & Banking',
     'description' => 'Financial services firm specializing in credit risk analysis and portfolio management.',
     'website' => 'https://finedge.com', 'location' => 'Pokhara, Nepal',
     'person' => 'Rajan Thapa', 'email' => 'rajan@finedge.com', 'phone' => '+977-61-455678'],
    ['name' => 'GreenBuild Inc', 'industry' => 'Civil Engineering',
     'description' => 'Infrastructure and construction company delivering residential and commercial projects.',
     'website' => 'https://greenbuild.np', 'location' => 'Lalitpur, Nepal',
     'person' => 'Anita Gurung', 'email' => 'anita@greenbuild.np', 'phone' => '+977-1-5544332'],
    ['name' => 'MediCare Systems', 'industry' => 'Healthcare',
     'description' => 'Hospital network modernizing patient records and digital health services.',
     'website' => 'https://medicare.np', 'location' => 'Bhaktapur, Nepal',
     'person' => 'Dr. Suman Rai', 'email' => 'suman@medicare.np', 'phone' => '+977-1-6611223'],
    ['name' => 'Nexa Digital', 'industry' => 'Digital Marketing',
     'description' => 'Agency handling SEO, content, and paid campaigns for local and international brands.',
     'website' => 'https://nexadigital.com', 'location' => 'Kathmandu, Nepal',
     'person' => 'Samir Karki', 'email' => 'samir@nexadigital.com', 'phone' => '+977-1-4422119'],
    ['name' => 'BluePeak Logistics', 'industry' => 'Logistics & Supply Chain',
     'description' => 'Freight and warehousing company moving goods across the country.',
     'website' => 'https://bluepeak.com', 'location' => 'Biratnagar, Nepal',
     'person' => 'Nisha Yadav', 'email' => 'nisha@bluepeak.com', 'phone' => '+977-21-533221'],
];

$companyIds = [];
foreach ($companies as $c) {
    $companyIds[$c['name']] = upsertCompany($db, $c);
}

// ---------------------------------------------------------------------------
// 2. Users (admin, company account, 4 students)
// ---------------------------------------------------------------------------
$adminPass   = password_hash('Admin@123', PASSWORD_DEFAULT);
$companyPass = password_hash('Company@123', PASSWORD_DEFAULT);
$studentPass = password_hash('Student@123', PASSWORD_DEFAULT);

$adminId = upsertUser($db, [
    'username' => 'admin', 'email' => 'admin@interntracker.com',
    'password_hash' => $adminPass, 'role' => 'admin',
    'full_name' => 'System Administrator', 'company_id' => null,
]);

$companyUserId = upsertUser($db, [
    'username' => 'demo_company', 'email' => 'demo_company@interntracker.com',
    'password_hash' => $companyPass, 'role' => 'company',
    'full_name' => 'Priya Sharma', 'company_id' => $companyIds['TechNova Solutions'],
]);

$students = [
    ['username' => 'demo_student1', 'email' => 'demo_student1@interntracker.com', 'full_name' => 'Aarav Shrestha'],
    ['username' => 'demo_student2', 'email' => 'demo_student2@interntracker.com', 'full_name' => 'Bipana Maharjan'],
    ['username' => 'demo_student3', 'email' => 'demo_student3@interntracker.com', 'full_name' => 'Kiran Tamang'],
    ['username' => 'demo_student4', 'email' => 'demo_student4@interntracker.com', 'full_name' => 'Laxmi Thapa'],
];

$studentIds = [];
foreach ($students as $s) {
    $studentIds[$s['email']] = upsertUser($db, [
        'username' => $s['username'], 'email' => $s['email'],
        'password_hash' => $studentPass, 'role' => 'student',
        'full_name' => $s['full_name'], 'company_id' => null,
    ]);
}

$allDemoUserIds    = array_merge(array_values($studentIds), [$companyUserId, $adminId]);
$allDemoCompanyIds = array_values($companyIds);

// ---------------------------------------------------------------------------
// 3. Reset demo-owned rows (idempotent re-runs). Cascades clean up
//    progress_logs / documents (internships) and applications (postings).
// ---------------------------------------------------------------------------
$stmt = $db->prepare("DELETE FROM internships WHERE student_id IN (?,?,?,?)");
$stmt->execute(array_values($studentIds));

$placeholders = implode(',', array_fill(0, count($allDemoCompanyIds), '?'));
$stmt = $db->prepare("DELETE FROM company_internships WHERE company_id IN ($placeholders)");
$stmt->execute($allDemoCompanyIds);

$userPlaceholders = implode(',', array_fill(0, count($allDemoUserIds), '?'));
$stmt = $db->prepare("DELETE FROM notifications WHERE user_id IN ($userPlaceholders)");
$stmt->execute($allDemoUserIds);

// ---------------------------------------------------------------------------
// 4. Student internships (10) across the last 6 months + next 2 months
// ---------------------------------------------------------------------------
$internships = [
    // Aarav — ongoing software dev at TechNova
    ['student' => 'demo_student1@interntracker.com', 'company' => 'TechNova Solutions',
     'title' => 'Software Development Intern', 'status' => 'ongoing',
     'start' => relDate('-5 months'), 'end' => relDate('+1 month'),
     'stipend' => 25000, 'work_mode' => 'hybrid',
     'supervisor' => 'Priya Sharma', 'supervisor_email' => 'priya@technova.io',
     'description' => 'Build REST APIs and admin tooling for the customer portal.',
     'notes' => 'Learning PHP and MySQL on the job.'],
    // Aarav — completed data analyst at FinEdge
    ['student' => 'demo_student1@interntracker.com', 'company' => 'FinEdge Corp',
     'title' => 'Data Analyst Intern', 'status' => 'completed',
     'start' => relDate('-6 months'), 'end' => relDate('-1 month'),
     'stipend' => 20000, 'work_mode' => 'onsite',
     'supervisor' => 'Rajan Thapa', 'supervisor_email' => 'rajan@finedge.com',
     'description' => 'Cleaned loan-portfolio data and built weekly reporting in SQL.',
     'notes' => 'Final report approved by the risk team.'],
    // Aarav — rejected IT support at MediCare
    ['student' => 'demo_student1@interntracker.com', 'company' => 'MediCare Systems',
     'title' => 'IT Support Intern', 'status' => 'rejected',
     'start' => relDate('-3 months'), 'end' => relDate('-2 months'),
     'stipend' => 18000, 'work_mode' => 'onsite',
     'supervisor' => 'Dr. Suman Rai', 'supervisor_email' => 'suman@medicare.np',
     'description' => 'Supporting the patient-record rollout.',
     'notes' => 'Position was put on hold due to budget.'],
    // Bipana — ongoing civil engineering at GreenBuild
    ['student' => 'demo_student2@interntracker.com', 'company' => 'GreenBuild Inc',
     'title' => 'Civil Engineering Intern', 'status' => 'ongoing',
     'start' => relDate('-2 months'), 'end' => relDate('+2 months'),
     'stipend' => 22000, 'work_mode' => 'onsite',
     'supervisor' => 'Anita Gurung', 'supervisor_email' => 'anita@greenbuild.np',
     'description' => 'Site inspections and quantity takeoffs for a residential project.',
     'notes' => 'Getting exposure to real site conditions.'],
    // Bipana — applied QA (future start)
    ['student' => 'demo_student2@interntracker.com', 'company' => 'TechNova Solutions',
     'title' => 'QA Automation Intern', 'status' => 'applied',
     'start' => relDate('+1 month'), 'end' => relDate('+2 months'),
     'stipend' => 20000, 'work_mode' => 'remote',
     'supervisor' => 'Priya Sharma', 'supervisor_email' => 'priya@technova.io',
     'description' => 'Automated regression tests for the customer portal.',
     'notes' => 'Awaiting interview date.'],
    // Kiran — accepted frontend at MediCare
    ['student' => 'demo_student3@interntracker.com', 'company' => 'MediCare Systems',
     'title' => 'Frontend Developer Intern', 'status' => 'accepted',
     'start' => relDate('-1 month'), 'end' => relDate('+3 months'),
     'stipend' => 21000, 'work_mode' => 'hybrid',
     'supervisor' => 'Dr. Suman Rai', 'supervisor_email' => 'suman@medicare.np',
     'description' => 'Patient portal UI using HTML, CSS and JavaScript.',
     'notes' => 'Offer letter signed.'],
    // Kiran — withdrawn site supervision at GreenBuild
    ['student' => 'demo_student3@interntracker.com', 'company' => 'GreenBuild Inc',
     'title' => 'Site Supervision Intern', 'status' => 'withdrawn',
     'start' => relDate('-4 months'), 'end' => relDate('-3 months'),
     'stipend' => 19000, 'work_mode' => 'onsite',
     'supervisor' => 'Anita Gurung', 'supervisor_email' => 'anita@greenbuild.np',
     'description' => 'Day-to-day site supervision on a commercial build.',
     'notes' => 'Withdrew after accepting the MediCare offer.'],
    // Laxmi — interview business analyst (future start)
    ['student' => 'demo_student4@interntracker.com', 'company' => 'FinEdge Corp',
     'title' => 'Business Analyst Intern', 'status' => 'interview',
     'start' => relDate('+2 months'), 'end' => relDate('+3 months'),
     'stipend' => 20000, 'work_mode' => 'remote',
     'supervisor' => 'Rajan Thapa', 'supervisor_email' => 'rajan@finedge.com',
     'description' => 'Requirements gathering and process documentation.',
     'notes' => 'Second-round interview scheduled.'],
    // Laxmi — ongoing cloud ops at TechNova
    ['student' => 'demo_student4@interntracker.com', 'company' => 'TechNova Solutions',
     'title' => 'Cloud Operations Intern', 'status' => 'ongoing',
     'start' => relDate('-3 months'), 'end' => relDate('+1 month'),
     'stipend' => 24000, 'work_mode' => 'hybrid',
     'supervisor' => 'Priya Sharma', 'supervisor_email' => 'priya@technova.io',
     'description' => 'Deployments, monitoring, and incident triage on the hosting stack.',
     'notes' => 'Hands-on with Linux and CI/CD.'],
    // Laxmi — completed marketing at GreenBuild
    ['student' => 'demo_student4@interntracker.com', 'company' => 'GreenBuild Inc',
     'title' => 'Marketing Intern', 'status' => 'completed',
     'start' => relDate('-5 months'), 'end' => relDate('-2 months'),
     'stipend' => 15000, 'work_mode' => 'onsite',
     'supervisor' => 'Anita Gurung', 'supervisor_email' => 'anita@greenbuild.np',
     'description' => 'Social media content and project photo documentation.',
     'notes' => 'Certification issued at the end of the term.'],
];

$internshipIdByTitle = [];
foreach ($internships as $i) {
    $internshipIdByTitle[$i['title']] = insertInternship($db, [
        'student_id'       => $studentIds[$i['student']],
        'company_id'       => $companyIds[$i['company']],
        'title'            => $i['title'],
        'description'      => $i['description'],
        'start_date'       => $i['start'],
        'end_date'         => $i['end'],
        'status'           => $i['status'],
        'stipend'          => $i['stipend'],
        'work_mode'        => $i['work_mode'],
        'supervisor'       => $i['supervisor'],
        'supervisor_email' => $i['supervisor_email'],
        'notes'            => $i['notes'],
    ]);
}

// ---------------------------------------------------------------------------
// 5. Weekly progress logs for ongoing internships
// ---------------------------------------------------------------------------
$logTasks = [
    'Set up the dev environment and reviewed the codebase.',
    'Implemented the first module and wrote unit tests.',
    'Attended sprint planning and picked up tickets.',
    'Integrated with the third-party API and handled edge cases.',
    'Refactored a legacy component for readability.',
    'Prepared the demo deck for the sprint review.',
    'Documented the changes and updated the project wiki.',
    'Shadowed the senior engineer during a deployment.',
];

$today = new DateTimeImmutable('today');
$insLog = $db->prepare("
    INSERT INTO progress_logs (internship_id, week_number, log_date, tasks_completed, skills_learned, challenges, hours_worked, rating)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
");
foreach ($internships as $i) {
    if ($i['status'] !== 'ongoing') {
        continue;
    }
    $start = new DateTimeImmutable($i['start']);
    $daysElapsed = (int) $start->diff($today)->days;
    $weeks = max(1, min((int) floor($daysElapsed / 7), count($logTasks)));

    for ($w = 1; $w <= $weeks; $w++) {
        $logDate = date('Y-m-d', strtotime($i['start'] . ' +' . ($w * 7) . ' days'));
        $insLog->execute([
            $internshipIdByTitle[$i['title']],
            $w,
            $logDate,
            $logTasks[$w - 1],
            'PHP, SQL, Git, teamwork; improved debugging speed.',
            'Debugging production issues and managing scope creep.',
            35.5,
            4,
        ]);
    }
}

// ---------------------------------------------------------------------------
// 6. Company job postings (6)
// ---------------------------------------------------------------------------
$postings = [
    ['company' => 'TechNova Solutions', 'title' => 'Full-Stack Developer Internship',
     'status' => 'active', 'location' => 'Kathmandu, Nepal', 'duration' => '3 months', 'stipend' => 25000,
     'requirements' => 'PHP, MySQL, JavaScript; basic Git; portfolio of any small project.',
     'description' => 'Build features end-to-end for the customer portal with mentorship from senior engineers.'],
    ['company' => 'FinEdge Corp', 'title' => 'Financial Analyst Internship',
     'status' => 'active', 'location' => 'Pokhara, Nepal', 'duration' => '3 months', 'stipend' => 20000,
     'requirements' => 'Excel, basic SQL, attention to detail; finance coursework preferred.',
     'description' => 'Support credit-risk analysis and help automate weekly reporting.'],
    ['company' => 'GreenBuild Inc', 'title' => 'Structural Engineering Internship',
     'status' => 'active', 'location' => 'Lalitpur, Nepal', 'duration' => '4 months', 'stipend' => 22000,
     'requirements' => 'AutoCAD familiarity; civil engineering final-year students.',
     'description' => 'Assist structural design and perform site quantity takeoffs.'],
    ['company' => 'MediCare Systems', 'title' => 'Healthcare IT Internship',
     'status' => 'pending', 'location' => 'Bhaktapur, Nepal', 'duration' => '3 months', 'stipend' => 21000,
     'requirements' => 'Basic networking and Windows administration.',
     'description' => 'Help roll out the new patient-record system across the hospital network.'],
    ['company' => 'Nexa Digital', 'title' => 'Digital Marketing Internship',
     'status' => 'active', 'location' => 'Kathmandu, Nepal', 'duration' => '2 months', 'stipend' => 15000,
     'requirements' => 'Social media fluency; copywriting samples a plus.',
     'description' => 'Run content calendars and track campaign performance for client accounts.'],
    ['company' => 'BluePeak Logistics', 'title' => 'Operations Internship',
     'status' => 'closed', 'location' => 'Biratnagar, Nepal', 'duration' => '3 months', 'stipend' => 18000,
     'requirements' => 'Data entry accuracy; spreadsheet skills.',
     'description' => 'Coordinate dispatch scheduling and maintain shipment records.'],
];

$postingIds = [];
foreach ($postings as $p) {
    $postingIds[$p['title']] = insertPosting($db, [
        'company_id'   => $companyIds[$p['company']],
        'title'        => $p['title'],
        'description'  => $p['description'],
        'requirements' => $p['requirements'],
        'location'     => $p['location'],
        'duration'     => $p['duration'],
        'stipend'      => $p['stipend'],
        'status'       => $p['status'],
    ]);
}

// ---------------------------------------------------------------------------
// 7. Applications (9) across statuses and the last 2 months
// ---------------------------------------------------------------------------
$coverLetters = [
    'I am a final-year student excited about this opportunity. I have built several small projects and am eager to learn from your team.',
    'My coursework and part-time work have given me solid hands-on experience. I would love the chance to contribute here.',
    'I am quick to learn and comfortable working in a team. This internship aligns perfectly with my career goals.',
];

$applications = [
    ['posting' => 'Full-Stack Developer Internship', 'student' => 'demo_student2@interntracker.com',
     'status' => 'under_review', 'applied_at' => relDateTime('-3 weeks'), 'cover' => 0,
     'notes' => 'Strong portfolio; shortlisted for a technical screen.'],
    ['posting' => 'Full-Stack Developer Internship', 'student' => 'demo_student3@interntracker.com',
     'status' => 'pending', 'applied_at' => relDateTime('-2 weeks'), 'cover' => 1, 'notes' => null],
    ['posting' => 'Full-Stack Developer Internship', 'student' => 'demo_student4@interntracker.com',
     'status' => 'accepted', 'applied_at' => relDateTime('-1 month'), 'cover' => 2,
     'notes' => 'Great fit; offer extended.'],
    ['posting' => 'Financial Analyst Internship', 'student' => 'demo_student1@interntracker.com',
     'status' => 'rejected', 'applied_at' => relDateTime('-5 weeks'), 'cover' => 1,
     'notes' => 'Already completed a term here; role filled.'],
    ['posting' => 'Financial Analyst Internship', 'student' => 'demo_student3@interntracker.com',
     'status' => 'pending', 'applied_at' => relDateTime('-1 week'), 'cover' => 2, 'notes' => null],
    ['posting' => 'Structural Engineering Internship', 'student' => 'demo_student1@interntracker.com',
     'status' => 'accepted', 'applied_at' => relDateTime('-6 weeks'), 'cover' => 0,
     'notes' => 'Offer extended; starting next month.'],
    ['posting' => 'Structural Engineering Internship', 'student' => 'demo_student4@interntracker.com',
     'status' => 'under_review', 'applied_at' => relDateTime('-2 weeks'), 'cover' => 1,
     'notes' => 'References being checked.'],
    ['posting' => 'Digital Marketing Internship', 'student' => 'demo_student2@interntracker.com',
     'status' => 'pending', 'applied_at' => relDateTime('-3 days'), 'cover' => 2, 'notes' => null],
    ['posting' => 'Digital Marketing Internship', 'student' => 'demo_student4@interntracker.com',
     'status' => 'pending', 'applied_at' => relDateTime('-5 days'), 'cover' => 0, 'notes' => null],
];

foreach ($applications as $a) {
    insertApplication($db, [
        'posting_id'  => $postingIds[$a['posting']],
        'student_id'  => $studentIds[$a['student']],
        'cover_letter'=> $coverLetters[$a['cover']],
        'notes'       => $a['notes'],
        'status'      => $a['status'],
        'applied_at'  => $a['applied_at'],
    ]);
}

// ---------------------------------------------------------------------------
// 8. In-app notifications for every demo account
// ---------------------------------------------------------------------------
insertNotification($db, $studentIds['demo_student1@interntracker.com'],
    'Progress log saved', 'Week 3 log for Software Development Intern was saved.', 'success', 1, relDateTime('-2 days'));
insertNotification($db, $studentIds['demo_student1@interntracker.com'],
    'Application status updated', 'Your application to Structural Engineering Internship at GreenBuild Inc was accepted.', 'success', 0, relDateTime('-6 days'));
insertNotification($db, $studentIds['demo_student1@interntracker.com'],
    'Milestone reached', 'Your internship at FinEdge Corp was marked completed.', 'info', 1, relDateTime('-3 weeks'));

insertNotification($db, $studentIds['demo_student2@interntracker.com'],
    'Application received', 'You applied to Full-Stack Developer Internship at TechNova Solutions.', 'info', 0, relDateTime('-3 weeks'));
insertNotification($db, $studentIds['demo_student2@interntracker.com'],
    'Application under review', 'Your application to Full-Stack Developer Internship is now under review.', 'warning', 0, relDateTime('-5 days'));
insertNotification($db, $studentIds['demo_student2@interntracker.com'],
    'Welcome to InternTrack', 'Track your internships, log weekly progress, and browse open positions.', 'success', 1, relDateTime('-1 month'));

insertNotification($db, $studentIds['demo_student3@interntracker.com'],
    'Offer accepted', 'Your internship at MediCare Systems starts soon. Upload your offer letter in the dashboard.', 'success', 0, relDateTime('-3 weeks'));
insertNotification($db, $studentIds['demo_student3@interntracker.com'],
    'Reminder', 'Add your first progress log for Frontend Developer Intern.', 'warning', 0, relDateTime('-2 days'));
insertNotification($db, $studentIds['demo_student3@interntracker.com'],
    'New position posted', 'Healthcare IT Internship is now open at MediCare Systems.', 'info', 1, relDateTime('-1 week'));

insertNotification($db, $studentIds['demo_student4@interntracker.com'],
    'Interview scheduled', 'Second-round interview for Business Analyst Intern at FinEdge Corp is scheduled.', 'info', 0, relDateTime('-1 day'));
insertNotification($db, $studentIds['demo_student4@interntracker.com'],
    'Application under review', 'Your application to Digital Marketing Internship at Nexa Digital is under review.', 'warning', 0, relDateTime('-4 days'));
insertNotification($db, $studentIds['demo_student4@interntracker.com'],
    'Progress log saved', 'Week 4 log for Cloud Operations Intern was saved.', 'success', 1, relDateTime('-1 week'));

insertNotification($db, $companyUserId,
    'New application', 'demo_student4 applied to Full-Stack Developer Internship.', 'info', 0, relDateTime('-2 days'));
insertNotification($db, $companyUserId,
    'Applications to review', 'You have 3 applications awaiting review.', 'warning', 0, relDateTime('-6 hours'));
insertNotification($db, $companyUserId,
    'Posting closed', 'Operations Internship is no longer accepting applications.', 'info', 1, relDateTime('-2 weeks'));

insertNotification($db, $adminId,
    'New company registered', 'BluePeak Logistics registered on the platform.', 'info', 1, relDateTime('-1 month'));
insertNotification($db, $adminId,
    'Weekly summary', '4 companies and 12 applications were recorded this week.', 'success', 0, relDateTime('-1 day'));

// ---------------------------------------------------------------------------
// 9. Summary
// ---------------------------------------------------------------------------
$summaryTables = ['users', 'companies', 'internships', 'company_internships', 'applications', 'progress_logs', 'notifications'];
$counts = [];
foreach ($summaryTables as $t) {
    $counts[$t] = (int) $db->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
}

echo "Seed complete. Demo accounts:\n";
echo "  student: demo_student1..4@interntracker.com / Student@123\n";
echo "  company: demo_company@interntracker.com / Company@123\n";
echo "  admin:   admin@interntracker.com / Admin@123\n\n";
echo "Row counts:\n";
foreach ($counts as $t => $n) {
    printf("  %-20s %d\n", $t, $n);
}
