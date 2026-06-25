import java.io.*;
import java.sql.*;
import java.time.LocalDate;
import java.time.format.DateTimeFormatter;
import java.util.*;

/**
 * InternshipReportGenerator.java
 * ──────────────────────────────
 * Java utility to generate CSV & summary reports for the
 * Internship Tracking System. Run from command line:
 *
 *   javac InternshipReportGenerator.java
 *   java InternshipReportGenerator [output_dir]
 *
 * Requires MySQL Connector/J on classpath:
 *   java -cp ".;mysql-connector-j-8.x.jar" InternshipReportGenerator
 */
public class InternshipReportGenerator {

    // ── DB Config ──────────────────────────────────────────────────────────────
    private static final String DB_URL  = "jdbc:mysql://localhost:3306/internship_tracker?useSSL=false&serverTimezone=UTC";
    private static final String DB_USER = "root";
    private static final String DB_PASS = "";

    private final Connection conn;
    private final String outputDir;

    public InternshipReportGenerator(String outputDir) throws SQLException {
        this.outputDir = outputDir;
        this.conn = DriverManager.getConnection(DB_URL, DB_USER, DB_PASS);
        System.out.println("✓ Connected to database.");
    }

    // ── MAIN ──────────────────────────────────────────────────────────────────
    public static void main(String[] args) {
        String out = args.length > 0 ? args[0] : "reports";
        new File(out).mkdirs();

        try {
            InternshipReportGenerator gen = new InternshipReportGenerator(out);
            gen.generateInternshipCSV();
            gen.generateStatusSummary();
            gen.generateStudentSummary();
            gen.generateProgressReport();
            System.out.println("\n✅ All reports generated in: " + out + "/");
        } catch (Exception e) {
            System.err.println("❌ Error: " + e.getMessage());
            e.printStackTrace();
        }
    }

    // ── REPORT 1: Full Internship CSV ─────────────────────────────────────────
    private void generateInternshipCSV() throws SQLException, IOException {
        String filename = outputDir + "/internships_" + today() + ".csv";
        String sql = """
            SELECT
                i.id, u.full_name AS student, u.email AS student_email,
                c.name AS company, c.industry, c.location,
                i.title, i.status, i.work_mode,
                i.start_date, i.end_date,
                DATEDIFF(i.end_date, i.start_date) AS duration_days,
                i.stipend,
                i.created_at
            FROM internships i
            JOIN users u ON i.student_id = u.id
            JOIN companies c ON i.company_id = c.id
            ORDER BY i.created_at DESC
            """;

        try (PrintWriter pw = new PrintWriter(new FileWriter(filename));
             Statement st = conn.createStatement();
             ResultSet rs = st.executeQuery(sql)) {

            // Header
            pw.println("ID,Student Name,Student Email,Company,Industry,Location," +
                       "Title,Status,Work Mode,Start Date,End Date,Duration (Days)," +
                       "Stipend (NPR),Created At");

            int count = 0;
            while (rs.next()) {
                pw.printf("%d,\"%s\",%s,\"%s\",%s,%s,\"%s\",%s,%s,%s,%s,%d,%.2f,%s%n",
                    rs.getInt("id"),
                    rs.getString("student"),
                    rs.getString("student_email"),
                    rs.getString("company"),
                    rs.getString("industry"),
                    rs.getString("location"),
                    rs.getString("title"),
                    rs.getString("status"),
                    rs.getString("work_mode"),
                    rs.getString("start_date"),
                    rs.getString("end_date"),
                    rs.getInt("duration_days"),
                    rs.getDouble("stipend"),
                    rs.getString("created_at")
                );
                count++;
            }
            System.out.println("✓ Internship CSV: " + count + " records → " + filename);
        }
    }

    // ── REPORT 2: Status Summary ──────────────────────────────────────────────
    private void generateStatusSummary() throws SQLException, IOException {
        String filename = outputDir + "/status_summary_" + today() + ".txt";
        String sql = "SELECT status, COUNT(*) AS cnt, AVG(stipend) AS avg_stipend " +
                     "FROM internships GROUP BY status ORDER BY cnt DESC";

        try (PrintWriter pw = new PrintWriter(new FileWriter(filename));
             Statement st = conn.createStatement();
             ResultSet rs = st.executeQuery(sql)) {

            pw.println("========================================");
            pw.println("  INTERNSHIP STATUS SUMMARY REPORT");
            pw.println("  Generated: " + LocalDate.now());
            pw.println("========================================");
            pw.printf("%-15s %10s %20s%n", "Status", "Count", "Avg Stipend (NPR)");
            pw.println("----------------------------------------");

            int total = 0;
            while (rs.next()) {
                String status = rs.getString("status");
                int cnt = rs.getInt("cnt");
                double avg = rs.getDouble("avg_stipend");
                pw.printf("%-15s %10d %20.2f%n", status, cnt, avg);
                total += cnt;
            }
            pw.println("----------------------------------------");
            pw.printf("%-15s %10d%n", "TOTAL", total);
            System.out.println("✓ Status summary → " + filename);
        }
    }

    // ── REPORT 3: Student Summary ─────────────────────────────────────────────
    private void generateStudentSummary() throws SQLException, IOException {
        String filename = outputDir + "/student_summary_" + today() + ".csv";
        String sql = """
            SELECT
                u.full_name, u.email,
                COUNT(i.id) AS total_applications,
                SUM(CASE WHEN i.status = 'accepted' OR i.status = 'ongoing' OR i.status = 'completed' THEN 1 ELSE 0 END) AS successful,
                MAX(i.stipend) AS highest_stipend,
                u.created_at AS joined
            FROM users u
            LEFT JOIN internships i ON u.id = i.student_id
            WHERE u.role = 'student'
            GROUP BY u.id
            ORDER BY total_applications DESC
            """;

        try (PrintWriter pw = new PrintWriter(new FileWriter(filename));
             Statement st = conn.createStatement();
             ResultSet rs = st.executeQuery(sql)) {

            pw.println("Student Name,Email,Total Applications,Successful,Highest Stipend,Joined");
            int count = 0;
            while (rs.next()) {
                pw.printf("\"%s\",%s,%d,%d,%.2f,%s%n",
                    rs.getString("full_name"),
                    rs.getString("email"),
                    rs.getInt("total_applications"),
                    rs.getInt("successful"),
                    rs.getDouble("highest_stipend"),
                    rs.getString("joined")
                );
                count++;
            }
            System.out.println("✓ Student summary: " + count + " students → " + filename);
        }
    }

    // ── REPORT 4: Progress/Hours Report ──────────────────────────────────────
    private void generateProgressReport() throws SQLException, IOException {
        String filename = outputDir + "/progress_report_" + today() + ".txt";
        String sql = """
            SELECT
                u.full_name, c.name AS company, i.title,
                COUNT(pl.id) AS log_count,
                SUM(pl.hours_worked) AS total_hours,
                AVG(pl.rating) AS avg_rating
            FROM progress_logs pl
            JOIN internships i ON pl.internship_id = i.id
            JOIN users u ON i.student_id = u.id
            JOIN companies c ON i.company_id = c.id
            GROUP BY i.id
            ORDER BY total_hours DESC
            """;

        try (PrintWriter pw = new PrintWriter(new FileWriter(filename));
             Statement st = conn.createStatement();
             ResultSet rs = st.executeQuery(sql)) {

            pw.println("========================================");
            pw.println("  INTERNSHIP PROGRESS REPORT");
            pw.println("  Generated: " + LocalDate.now());
            pw.println("========================================");
            pw.printf("%-20s %-20s %-25s %5s %8s %6s%n",
                "Student","Company","Role","Logs","Hours","Rating");
            pw.println("─".repeat(90));

            while (rs.next()) {
                pw.printf("%-20s %-20s %-25s %5d %8.1f %6.1f%n",
                    truncate(rs.getString("full_name"), 19),
                    truncate(rs.getString("company"), 19),
                    truncate(rs.getString("title"), 24),
                    rs.getInt("log_count"),
                    rs.getDouble("total_hours"),
                    rs.getDouble("avg_rating")
                );
            }
            System.out.println("✓ Progress report → " + filename);
        }
    }

    private String today() {
        return LocalDate.now().format(DateTimeFormatter.ofPattern("yyyyMMdd"));
    }

    private String truncate(String s, int max) {
        if (s == null) return "";
        return s.length() <= max ? s : s.substring(0, max - 1) + "…";
    }
}
