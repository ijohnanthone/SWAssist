INSERT IGNORE INTO users (username, password, full_name, role) VALUES
('demo_admin', '$2y$10$DD87g.1yt8cvlfSsWTk87.dY.qMsJ.e/1kPb1LBO75lbW9wTbYy4u', 'Demo Administrator', 'admin'),
('demo_supervisor', '$2y$10$E7ET4hNanaMRzX6FnIuwe1bShKiAUHVtaCDHD81cU.B5SjnK86UK', 'Demo Supervisor', 'supervisor'),
('demo_student', '$2y$10$w6V/UQSBfAnuK7vnU95GhO/v/UPYMl07kUUtJvSwPhIT5zVaZ40yi', 'Demo Student', 'student');

INSERT IGNORE INTO cases (case_code, client_name, age, sex, civil_status, education, occupation, status, assigned_student_id, created_by)
SELECT 'DEMO-001', 'Alex Rivera', 19, 'Prefer not to say', 'Single', 'Senior high school', 'Student', 'Active', id, id FROM users WHERE username = 'demo_student';
INSERT IGNORE INTO cases (case_code, client_name, age, sex, civil_status, education, status, assigned_student_id, created_by)
SELECT 'DEMO-002', 'Jamie Santos', 34, 'Prefer not to say', 'Married', 'College graduate', 'Draft', id, id FROM users WHERE username = 'demo_student';

INSERT IGNORE INTO case_studies (case_id, presenting_problem, background_client, goal, assessment, prepared_by)
SELECT id, 'Fictional training record for demonstrating documentation flow.', 'Alex is a fictional student profile used for local testing.', 'Support the client in identifying practical next steps.', 'This is sample content only and is not a professional assessment.', 'Demo Student' FROM cases WHERE case_code = 'DEMO-001';

INSERT IGNORE INTO family_members (case_id, name, relationship, age, education, occupation, civil_status)
SELECT id, 'Morgan Rivera', 'Parent', 45, 'College graduate', 'Office staff', 'Married' FROM cases WHERE case_code = 'DEMO-001';

INSERT IGNORE INTO treatment_plans (case_id, problems, objectives, activities, responsible_person, time_frame, expected_output)
SELECT id, 'Sample planning item', 'Identify available supports', 'Information gathering and review', 'Demo Student', '30 days', 'Documented options' FROM cases WHERE case_code = 'DEMO-001';

INSERT IGNORE INTO activities (case_id, title, description, activity_date, location, responsible_person, result_output, created_by)
SELECT c.id, 'Initial documentation review', 'Fictional activity used to demonstrate the progress log.', CURRENT_DATE, 'Campus office', 'Demo Student', 'Sample note recorded', u.id FROM cases c JOIN users u ON u.username = 'demo_student' WHERE c.case_code = 'DEMO-001';