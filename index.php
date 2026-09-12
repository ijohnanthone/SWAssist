<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';

$page = $_GET['page'] ?? 'dashboard';

if (!isset($_GET['page']) && !user()) {
	redirect('index.php?page=login');
}

if ($page === 'logout') {
	send_no_cache_headers();
	$_SESSION = [];
	if (ini_get('session.use_cookies')) {
		$params = session_get_cookie_params();
		setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
	}
	session_unset();
	session_destroy();
	redirect('index.php?page=login');
}

if ($page === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
	verify_csrf();
	$username = trim($_POST['username'] ?? '');
	$password = $_POST['password'] ?? '';
	$account = query($conn, 'SELECT id, username, password, full_name, role FROM users WHERE username = ?', 's', [$username])->fetch_assoc();
	if ($account && password_verify($password, $account['password'])) {
		session_regenerate_id(true);
		unset($account['password']);
		$_SESSION['user'] = $account;
		redirect('index.php');
	}
	flash('error', 'The username or password is incorrect.');
	redirect('index.php?page=login');
}

if ($page === 'register' && $_SERVER['REQUEST_METHOD'] === 'POST') {
	verify_csrf();
	$username = trim($_POST['username'] ?? '');
	$fullName = trim($_POST['full_name'] ?? '');
	$password = $_POST['password'] ?? '';
	if ($username === '' || $fullName === '' || strlen($password) < 8) {
		flash('error', 'Provide a name, username, and password of at least 8 characters.');
		redirect('index.php?page=register');
	}
	$statement = $conn->prepare("INSERT INTO users (username, password, full_name, role) VALUES (?, ?, ?, 'student')");
	$hash = password_hash($password, PASSWORD_DEFAULT);
	$statement->bind_param('sss', $username, $hash, $fullName);
	if (!$statement->execute()) {
		flash('error', $statement->errno === 1062 ? 'That username is already in use.' : 'Registration could not be completed.');
	} else {
		flash('success', 'Account created. You can now sign in.');
	}
	redirect('index.php?page=login');
}

if (in_array($page, ['login', 'register'], true)) {
	render_header($page === 'login' ? 'Sign in' : 'Create account');
	?>
	<section class="auth-shell"><div class="auth-copy"><p class="eyebrow">SWAssist</p><h1>Documentation that keeps the work moving.</h1><p>Organize case records, progress notes, and Social Case Study Reports in one private workspace.</p></div><form class="form-card" method="post"><h2><?= $page === 'login' ? 'Welcome back' : 'Create a student account' ?></h2><?php if ($page === 'login'): ?><label>Username<input name="username" required autofocus></label><label>Password<input type="password" name="password" required></label><button class="button primary" type="submit">Sign in</button><p class="muted">New student? <a href="index.php?page=register">Create an account</a></p><?php else: ?><label>Full name<input name="full_name" required></label><label>Username<input name="username" required></label><label>Password<input type="password" name="password" minlength="8" required></label><button class="button primary" type="submit">Create account</button><p class="muted"><a href="index.php?page=login">Back to sign in</a></p><?php endif; ?><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"></form></section>
	<?php render_footer(); exit;
}

require_auth();
$currentUser = user();

if ($page === 'upload' && $_SERVER['REQUEST_METHOD'] === 'POST') {
	verify_csrf();
	$caseId = (int) ($_POST['case_id'] ?? 0);
	if (!$caseId || !can_access_case($conn, $caseId, $currentUser['id'], $currentUser['role'])) { http_response_code(403); exit('Not authorized.'); }
	$file = $_FILES['attachment'] ?? null;
	$allowed = ['pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
	$extension = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
	$mime = $file && is_uploaded_file($file['tmp_name']) ? (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']) : '';
	if (!$file || $file['error'] !== UPLOAD_ERR_OK || $file['size'] > 5 * 1024 * 1024 || !isset($allowed[$extension]) || $allowed[$extension] !== $mime) {
		flash('error', 'Upload rejected. Use a PDF, JPG, PNG, or DOCX file up to 5 MB.');
	} else {
		$storedName = bin2hex(random_bytes(16)) . '.' . $extension;
		$directory = __DIR__ . '/uploads';
		if (!is_dir($directory)) { mkdir($directory, 0750, true); }
		move_uploaded_file($file['tmp_name'], $directory . '/' . $storedName);
		query($conn, 'INSERT INTO attachments (case_id,original_name,stored_name,mime_type,file_size,uploaded_by) VALUES (?,?,?,?,?,?)', 'isssii', [$caseId, basename($file['name']), $storedName, $mime, $file['size'], $currentUser['id']]);
		flash('success', 'Attachment uploaded.');
	}
	redirect('index.php?page=case&id=' . $caseId);
}

if ($page === 'download') {
	$attachmentId = (int) ($_GET['id'] ?? 0);
	$attachment = query($conn, 'SELECT a.* FROM attachments a WHERE a.id = ?', 'i', [$attachmentId])->fetch_assoc();
	if (!$attachment || !can_access_case($conn, (int) $attachment['case_id'], $currentUser['id'], $currentUser['role'])) { http_response_code(404); exit('File not found.'); }
	$path = __DIR__ . '/uploads/' . basename($attachment['stored_name']);
	if (!is_file($path)) { http_response_code(404); exit('File not found.'); }
	header('Content-Type: ' . $attachment['mime_type']); header('Content-Length: ' . filesize($path)); header('Content-Disposition: attachment; filename="' . str_replace('"', '', basename($attachment['original_name'])) . '"'); readfile($path); exit;
}

if ($page === 'case-study') {
	require __DIR__ . '/case-study.php';
	exit;
}

if ($page === 'case-create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
	verify_csrf();
	$caseCode = trim($_POST['case_code'] ?? '');
	$clientName = trim($_POST['client_name'] ?? '');
	if ($caseCode === '' || $clientName === '') { flash('error', 'Case ID and client name are required.'); redirect('index.php?page=case-create'); }
	$statement = $conn->prepare("INSERT INTO cases (case_code, client_name, age, sex, civil_status, religious_affiliation, date_of_birth, place_of_birth, education, occupation, monthly_income, present_address, home_address, status, assigned_student_id, created_by) VALUES (?, ?, NULLIF(?, ''), ?, ?, ?, NULLIF(?, ''), ?, ?, ?, NULLIF(?, ''), ?, ?, ?, ?, ?)");
	$age = $_POST['age'] ?? ''; $income = $_POST['monthly_income'] ?? ''; $student = (int) ($_POST['assigned_student_id'] ?? $currentUser['id']); $status = $_POST['status'] ?? 'Draft';
	$sex = $_POST['sex'] ?? ''; $civilStatus = $_POST['civil_status'] ?? ''; $religion = $_POST['religious_affiliation'] ?? ''; $dateOfBirth = $_POST['date_of_birth'] ?? ''; $placeOfBirth = $_POST['place_of_birth'] ?? ''; $education = $_POST['education'] ?? ''; $occupation = $_POST['occupation'] ?? ''; $presentAddress = $_POST['present_address'] ?? ''; $homeAddress = $_POST['home_address'] ?? '';
	$statement->bind_param('ssssssssssssssii', $caseCode, $clientName, $age, $sex, $civilStatus, $religion, $dateOfBirth, $placeOfBirth, $education, $occupation, $income, $presentAddress, $homeAddress, $status, $student, $currentUser['id']);
	if (!$statement->execute()) { flash('error', $statement->errno === 1062 ? 'That case ID already exists.' : 'The case could not be saved.'); redirect('index.php?page=case-create'); }
	flash('success', 'Case created.'); redirect('index.php?page=case&id=' . $statement->insert_id);
}

if ($page === 'case-create') {
	render_header('New case');
	$students = query($conn, "SELECT id, full_name FROM users WHERE role = 'student' ORDER BY full_name");
	?><div class="page-head"><div><p class="eyebrow">Case management</p><h1>New case</h1></div><a class="button" href="index.php?page=cases">Back to cases</a></div><form class="form-card wide" method="post"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><div class="form-grid"><label>Case ID *<input name="case_code" required placeholder="e.g. SW-2026-001"></label><label>Client name *<input name="client_name" required></label><label>Age<input type="number" name="age" min="0" max="120"></label><label>Sex<input name="sex"></label><label>Civil status<input name="civil_status"></label><label>Religious affiliation<input name="religious_affiliation"></label><label>Date of birth<input type="date" name="date_of_birth"></label><label>Place of birth<input name="place_of_birth"></label><label>Highest educational attainment<input name="education"></label><label>Occupation<input name="occupation"></label><label>Monthly income<input type="number" step="0.01" name="monthly_income"></label><label>Status<select name="status"><option>Draft</option><option>Active</option><option>Completed</option></select></label><label class="span-2">Present address<textarea name="present_address" rows="2"></textarea></label><label class="span-2">Home address<textarea name="home_address" rows="2"></textarea></label><label>Assigned student<select name="assigned_student_id"><?php while ($student = $students->fetch_assoc()): ?><option value="<?= e((string)$student['id']) ?>" <?= $student['id'] == $currentUser['id'] ? 'selected' : '' ?>><?= e($student['full_name']) ?></option><?php endwhile; ?></select></label></div><button class="button primary" type="submit">Create case</button></form><?php render_footer(); exit;
}

if ($page === 'cases') {
	$search = trim($_GET['search'] ?? ''); $statusFilter = $_GET['status'] ?? '';
	$where = $currentUser['role'] === 'student' ? 'WHERE c.assigned_student_id = ?' : 'WHERE 1=1'; $types = $currentUser['role'] === 'student' ? 'i' : ''; $params = $currentUser['role'] === 'student' ? [$currentUser['id']] : [];
	if ($search !== '') { $where .= ' AND (c.case_code LIKE ? OR c.client_name LIKE ?)'; $types .= 'ss'; $params[] = "%$search%"; $params[] = "%$search%"; }
	if (in_array($statusFilter, ['Draft','Active','Completed','Archived'], true)) { $where .= ' AND c.status = ?'; $types .= 's'; $params[] = $statusFilter; }
	$cases = query($conn, "SELECT c.*, u.full_name AS student_name FROM cases c LEFT JOIN users u ON u.id = c.assigned_student_id $where ORDER BY c.updated_at DESC", $types, $params);
	render_header('Cases'); ?><div class="page-head"><div><p class="eyebrow">Records</p><h1>Cases</h1><p class="muted">Search by case ID or permitted client identifier.</p></div><a class="button primary" href="index.php?page=case-create">+ New case</a></div><form class="toolbar"><input name="search" value="<?= e($search) ?>" placeholder="Search cases"><select name="status"><option value="">All statuses</option><?php foreach (['Draft','Active','Completed','Archived'] as $status): ?><option <?= $statusFilter === $status ? 'selected' : '' ?>><?= $status ?></option><?php endforeach; ?></select><button class="button" type="submit">Filter</button></form><div class="table-wrap"><table><thead><tr><th>Case ID</th><th>Client</th><th>Status</th><th>Assigned student</th><th>Updated</th><th></th></tr></thead><tbody><?php while ($case = $cases->fetch_assoc()): ?><tr><td><strong><?= e($case['case_code']) ?></strong></td><td><?= e($case['client_name']) ?></td><td><span class="badge <?= strtolower($case['status']) ?>"><?= e($case['status']) ?></span></td><td><?= e($case['student_name'] ?? 'Unassigned') ?></td><td><?= e(date('M j, Y', strtotime($case['updated_at']))) ?></td><td><a class="text-link" href="index.php?page=case&id=<?= e((string)$case['id']) ?>">Open</a></td></tr><?php endwhile; ?></tbody></table></div><?php render_footer(); exit;
}

if ($page === 'case') {
	$caseId = (int) ($_GET['id'] ?? 0);
	if (!$caseId || !can_access_case($conn, $caseId, $currentUser['id'], $currentUser['role'])) { http_response_code(404); exit('Case not found.'); }
	$case = query($conn, 'SELECT c.*, u.full_name AS student_name FROM cases c LEFT JOIN users u ON u.id = c.assigned_student_id WHERE c.id = ?', 'i', [$caseId])->fetch_assoc();
	$activities = query($conn, 'SELECT * FROM activities WHERE case_id = ? ORDER BY activity_date DESC, id DESC', 'i', [$caseId]);
	$attachments = query($conn, 'SELECT * FROM attachments WHERE case_id = ? ORDER BY created_at DESC', 'i', [$caseId]);
	render_header('Case ' . $case['case_code']); ?>
	<div class="page-head"><div><p class="eyebrow">Case <?= e($case['case_code']) ?></p><h1><?= e($case['client_name']) ?></h1><span class="badge <?= strtolower($case['status']) ?>"><?= e($case['status']) ?></span></div><div class="actions"><a class="button" href="index.php?page=case-edit&id=<?= $caseId ?>">Edit case</a><a class="button" href="index.php?page=case-study&id=<?= $caseId ?>">Case study</a><a class="button" href="index.php?page=activity-create&case_id=<?= $caseId ?>">Add activity</a></div></div>
	<div class="stats"><div><strong><?= e((string) ($case['age'] ?? '—')) ?></strong><span>Age</span></div><div><strong><?= e((string) $activities->num_rows) ?></strong><span>Visits</span></div><div><strong><?= e(date('M j, Y', strtotime($case['updated_at']))) ?></strong><span>Last updated</span></div></div>
	<div class="detail-grid"><section class="panel"><h2>Identifying information</h2><dl class="details"><?php foreach (['sex'=>'Sex','civil_status'=>'Civil status','religious_affiliation'=>'Religious affiliation','date_of_birth'=>'Date of birth','place_of_birth'=>'Place of birth','education'=>'Education','occupation'=>'Occupation','monthly_income'=>'Monthly income','present_address'=>'Present address','home_address'=>'Home address'] as $key => $label): ?><div><dt><?= $label ?></dt><dd><?= e((string) ($case[$key] ?: 'Not recorded')) ?></dd></div><?php endforeach; ?></dl></section>
	<section class="panel"><h2>Visits and progress notes</h2><?php if (!$activities->num_rows): ?><p class="muted">No visits recorded yet.</p><?php endif; ?><?php while ($activity = $activities->fetch_assoc()): ?><details class="timeline-item"><summary><strong><?= e($activity['title']) ?></strong><time><?= e(date('M j, Y', strtotime($activity['activity_date']))) ?></time></summary><div class="activity-content"><p><?= nl2br(e($activity['description'])) ?></p><dl class="activity-details"><?php foreach (['location'=>'Location','responsible_person'=>'Responsible person','result_output'=>'Result / output','notes'=>'Notes'] as $key => $label): ?><?php if (trim((string) $activity[$key]) !== ''): ?><div><dt><?= $label ?></dt><dd><?= nl2br(e($activity[$key])) ?></dd></div><?php endif; ?><?php endforeach; ?></dl><a class="text-link activity-edit-link" href="index.php?page=activity-edit&id=<?= $activity['id'] ?>">Edit this visit</a></div></details><?php endwhile; ?></section></div>
	<section class="panel"><h2>Attachments</h2><form method="post" enctype="multipart/form-data"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="case_id" value="<?= $caseId ?>"><input type="file" name="attachment" accept=".pdf,.jpg,.jpeg,.png,.docx" required><button type="submit" class="button" formaction="index.php?page=upload">Upload file</button></form><?php while ($attachment = $attachments->fetch_assoc()): ?><p><a class="text-link" href="index.php?page=download&id=<?= $attachment['id'] ?>"><?= e($attachment['original_name']) ?></a> <span class="muted"><?= e(number_format($attachment['file_size'] / 1024, 1)) ?> KB</span></p><?php endwhile; ?></section>
	<?php render_footer(); exit;
}

if ($page === 'case-legacy') {
	$caseId = (int) ($_GET['id'] ?? 0);
	if (!$caseId || !can_access_case($conn, $caseId, $currentUser['id'], $currentUser['role'])) { http_response_code(404); exit('Case not found.'); }
	$case = query($conn, 'SELECT c.*, u.full_name AS student_name FROM cases c LEFT JOIN users u ON u.id = c.assigned_student_id WHERE c.id = ?', 'i', [$caseId])->fetch_assoc();
	$activities = query($conn, 'SELECT * FROM activities WHERE case_id = ? ORDER BY activity_date DESC, id DESC', 'i', [$caseId]); $attachments = query($conn, 'SELECT * FROM attachments WHERE case_id = ? ORDER BY created_at DESC', 'i', [$caseId]);
	render_header('Case ' . $case['case_code']); ?><div class="page-head"><div><p class="eyebrow">Case <?= e($case['case_code']) ?></p><h1><?= e($case['client_name']) ?></h1><span class="badge <?= strtolower($case['status']) ?>"><?= e($case['status']) ?></span></div><div class="actions"><a class="button" href="index.php?page=case-edit&id=<?= $caseId ?>">Edit case</a><a class="button" href="index.php?page=case-study&id=<?= $caseId ?>">Case study</a><a class="button" href="index.php?page=activity-create&case_id=<?= $caseId ?>">Add activity</a></div></div><div class="stats"><div><strong><?= e((string)($case['age'] ?? '—')) ?></strong><span>Age</span></div><div><strong><?= e((string)$activities->num_rows) ?></strong><span>Activities</span></div><div><strong><?= e(date('M j, Y', strtotime($case['updated_at']))) ?></strong><span>Last updated</span></div></div><div class="detail-grid"><section class="panel"><h2>Identifying information</h2><dl class="details"><?php foreach (['sex'=>'Sex','civil_status'=>'Civil status','religious_affiliation'=>'Religious affiliation','date_of_birth'=>'Date of birth','place_of_birth'=>'Place of birth','education'=>'Education','occupation'=>'Occupation','monthly_income'=>'Monthly income','present_address'=>'Present address','home_address'=>'Home address'] as $key=>$label): ?><div><dt><?= $label ?></dt><dd><?= e((string)($case[$key] ?: 'Not recorded')) ?></dd></div><?php endforeach; ?></dl></section><section class="panel"><h2>Progress notes</h2><?php if (!$activities->num_rows): ?><p class="muted">No activities recorded yet.</p><?php endif; while ($activity = $activities->fetch_assoc()): ?><article class="timeline-item"><strong><?= e($activity['title']) ?></strong><time><?= e(date('M j, Y', strtotime($activity['activity_date']))) ?></time><p><?= nl2br(e($activity['description'])) ?></p></article><?php endwhile; ?></section></div><section class="panel"><h2>Attachments</h2><form method="post" enctype="multipart/form-data"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="case_id" value="<?= $caseId ?>"><input type="file" name="attachment" accept=".pdf,.jpg,.jpeg,.png,.docx" required><button class="button" type="submit" formaction="index.php?page=upload">Upload file</button></form><?php while ($attachment = $attachments->fetch_assoc()): ?><p><a class="text-link" href="index.php?page=download&id=<?= $attachment['id'] ?>"><?= e($attachment['original_name']) ?></a> <span class="muted"><?= e(number_format($attachment['file_size'] / 1024, 1)) ?> KB</span></p><?php endwhile; ?></section><?php render_footer(); exit;
}

if ($page === 'case-edit') {
	$caseId = (int) ($_GET['id'] ?? 0);
	if (!$caseId || !can_access_case($conn, $caseId, $currentUser['id'], $currentUser['role'])) { http_response_code(404); exit('Case not found.'); }
	$case = query($conn, 'SELECT * FROM cases WHERE id = ?', 'i', [$caseId])->fetch_assoc();
	if ($_SERVER['REQUEST_METHOD'] === 'POST') {
		verify_csrf();
		$clientName = trim($_POST['client_name'] ?? ''); $status = $_POST['status'] ?? 'Draft'; $age = $_POST['age'] ?? ''; $income = $_POST['monthly_income'] ?? '';
		$statement = $conn->prepare("UPDATE cases SET client_name=?, age=NULLIF(?, ''), sex=?, civil_status=?, religious_affiliation=?, date_of_birth=NULLIF(?, ''), place_of_birth=?, education=?, occupation=?, monthly_income=NULLIF(?, ''), present_address=?, home_address=?, status=? WHERE id=?");
		$sex = $_POST['sex'] ?? ''; $civilStatus = $_POST['civil_status'] ?? ''; $religion = $_POST['religious_affiliation'] ?? ''; $dateOfBirth = $_POST['date_of_birth'] ?? ''; $placeOfBirth = $_POST['place_of_birth'] ?? ''; $education = $_POST['education'] ?? ''; $occupation = $_POST['occupation'] ?? ''; $presentAddress = $_POST['present_address'] ?? ''; $homeAddress = $_POST['home_address'] ?? '';
		$statement->bind_param('sssssssssssssi', $clientName, $age, $sex, $civilStatus, $religion, $dateOfBirth, $placeOfBirth, $education, $occupation, $income, $presentAddress, $homeAddress, $status, $caseId);
		if (!$statement->execute()) {
			error_log('SWAssist case update failed: ' . $statement->error);
			flash('error', 'The case could not be saved.');
			redirect('index.php?page=case-edit&id=' . $caseId);
		}
		flash('success', 'Case updated.'); redirect('index.php?page=case&id=' . $caseId);
	}
	render_header('Edit case'); ?><div class="page-head"><div><p class="eyebrow">Case <?= e($case['case_code']) ?></p><h1>Edit case</h1></div></div><form class="form-card wide" method="post"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><div class="form-grid"><label>Case ID<input value="<?= e($case['case_code']) ?>" disabled></label><label>Client name *<input name="client_name" value="<?= e($case['client_name']) ?>" required></label><label>Age<input name="age" type="number" value="<?= e((string)$case['age']) ?>"></label><label>Sex<input name="sex" value="<?= e($case['sex']) ?>"></label><label>Civil status<input name="civil_status" value="<?= e($case['civil_status']) ?>"></label><label>Religious affiliation<input name="religious_affiliation" value="<?= e($case['religious_affiliation']) ?>"></label><label>Date of birth<input name="date_of_birth" type="date" value="<?= e($case['date_of_birth']) ?>"></label><label>Place of birth<input name="place_of_birth" value="<?= e($case['place_of_birth']) ?>"></label><label>Education<input name="education" value="<?= e($case['education']) ?>"></label><label>Occupation<input name="occupation" value="<?= e($case['occupation']) ?>"></label><label>Monthly income<input name="monthly_income" value="<?= e((string)$case['monthly_income']) ?>"></label><label>Status<select name="status"><?php foreach (['Draft','Active','Completed','Archived'] as $status): ?><option <?= $case['status'] === $status ? 'selected' : '' ?>><?= $status ?></option><?php endforeach; ?></select></label><label class="span-2">Present address<textarea name="present_address"><?= e($case['present_address']) ?></textarea></label><label class="span-2">Home address<textarea name="home_address"><?= e($case['home_address']) ?></textarea></label></div><button class="button primary">Save changes</button></form><?php render_footer(); exit;
}

if ($page === 'activity-view') {
	$activityId = (int) ($_GET['id'] ?? 0);
	$activity = query($conn, 'SELECT a.*, c.case_code, c.client_name FROM activities a JOIN cases c ON c.id = a.case_id WHERE a.id = ?', 'i', [$activityId])->fetch_assoc();
	if (!$activity || !can_access_case($conn, (int) $activity['case_id'], $currentUser['id'], $currentUser['role'])) { http_response_code(404); exit('Activity not found.'); }
	$activityRows = query($conn, 'SELECT id, title, activity_date FROM activities WHERE case_id = ? ORDER BY activity_date DESC, id DESC', 'i', [(int) $activity['case_id']])->fetch_all(MYSQLI_ASSOC);
	$currentIndex = array_search($activityId, array_column($activityRows, 'id'), true);
	$previous = $currentIndex !== false && $currentIndex > 0 ? $activityRows[$currentIndex - 1] : null;
	$next = $currentIndex !== false && $currentIndex < count($activityRows) - 1 ? $activityRows[$currentIndex + 1] : null;
	render_header('Visit: ' . $activity['title']); ?>
	<div class="page-head"><div><p class="eyebrow">Visit record · <?= e($activity['case_code']) ?></p><h1><?= e($activity['title']) ?></h1><p class="muted"><?= e($activity['client_name']) ?> · <?= e(date('F j, Y', strtotime($activity['activity_date']))) ?></p></div><div class="actions"><a class="button" href="index.php?page=case&id=<?= (int) $activity['case_id'] ?>">Back to case</a><a class="button primary" href="index.php?page=activity-edit&id=<?= $activityId ?>">Edit visit</a></div></div>
	<nav class="activity-nav" aria-label="Visit navigation"><?php if ($previous): ?><a class="button" href="index.php?page=activity-view&id=<?= $previous['id'] ?>">Previous visit</a><?php else: ?><span></span><?php endif; ?><?php if ($next): ?><a class="button" href="index.php?page=activity-view&id=<?= $next['id'] ?>">Next visit</a><?php endif; ?></nav>
	<article class="activity-view"><section><h2>Description</h2><div class="activity-prose"><?= nl2br(e($activity['description'])) ?: '<span class="muted">No description recorded.</span>' ?></div></section><dl class="activity-view-details"><?php foreach (['location'=>'Location','responsible_person'=>'Responsible person','result_output'=>'Result / output','notes'=>'Notes'] as $key => $label): ?><div><dt><?= $label ?></dt><dd><?= trim((string) $activity[$key]) !== '' ? nl2br(e($activity[$key])) : '<span class="muted">Not recorded</span>' ?></dd></div><?php endforeach; ?></dl></article>
	<?php render_footer(); exit;
}

if ($page === 'activity-edit') {
	$activityId = (int) ($_GET['id'] ?? 0);
	$activity = query($conn, 'SELECT * FROM activities WHERE id = ?', 'i', [$activityId])->fetch_assoc();
	if (!$activity || !can_access_case($conn, (int) $activity['case_id'], $currentUser['id'], $currentUser['role'])) { http_response_code(404); exit('Activity not found.'); }
	if ($_SERVER['REQUEST_METHOD'] === 'POST') {
		verify_csrf();
		$title = trim($_POST['title'] ?? ''); $description = trim($_POST['description'] ?? ''); $activityDate = $_POST['activity_date'] ?? '';
		$location = trim($_POST['location'] ?? ''); $responsiblePerson = trim($_POST['responsible_person'] ?? ''); $resultOutput = trim($_POST['result_output'] ?? ''); $notes = trim($_POST['notes'] ?? '');
		$statement = $conn->prepare('UPDATE activities SET title=?, description=?, activity_date=?, location=?, responsible_person=?, result_output=?, notes=? WHERE id=?');
		$statement->bind_param('sssssssi', $title, $description, $activityDate, $location, $responsiblePerson, $resultOutput, $notes, $activityId);
		if (!$statement->execute()) { error_log('SWAssist activity update failed: ' . $statement->error); flash('error', 'The activity could not be saved.'); redirect('index.php?page=activity-edit&id=' . $activityId); }
		flash('success', 'Activity updated.'); redirect('index.php?page=case&id=' . (int) $activity['case_id']);
	}
	render_header('Edit activity'); ?><div class="page-head"><div><p class="eyebrow">Visit record</p><h1>Edit activity</h1></div><a class="button" href="index.php?page=case&id=<?= (int) $activity['case_id'] ?>">Back to case</a></div><form class="form-card wide" method="post"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><div class="form-grid"><label>Activity title<input name="title" value="<?= e($activity['title']) ?>" required></label><label>Date<input name="activity_date" type="date" value="<?= e($activity['activity_date']) ?>" required></label><label>Location<input name="location" value="<?= e($activity['location']) ?>"></label><label>Responsible person<input name="responsible_person" value="<?= e($activity['responsible_person']) ?>"></label><label class="span-2">Description<textarea name="description" rows="5"><?= e($activity['description']) ?></textarea></label><label class="span-2">Result / output<textarea name="result_output" rows="5"><?= e($activity['result_output']) ?></textarea></label><label class="span-2">Notes<textarea name="notes" rows="5"><?= e($activity['notes']) ?></textarea></label></div><button class="button primary" type="submit">Save activity</button></form><?php render_footer(); exit;
}

if ($page === 'activity-create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
	verify_csrf(); $caseId = (int) $_POST['case_id'];
	if (!can_access_case($conn, $caseId, $currentUser['id'], $currentUser['role'])) { http_response_code(403); exit('Not authorized.'); }
	query($conn, 'INSERT INTO activities (case_id,title,description,activity_date,location,responsible_person,result_output,notes,created_by) VALUES (?,?,?,?,?,?,?,?,?)', 'isssssssi', [$caseId, trim($_POST['title']), trim($_POST['description']), $_POST['activity_date'], trim($_POST['location']), trim($_POST['responsible_person']), trim($_POST['result_output']), trim($_POST['notes']), $currentUser['id']]);
	flash('success', 'Activity recorded.'); redirect('index.php?page=case&id=' . $caseId);
}
if ($page === 'activity-create') { $caseId = (int) ($_GET['case_id'] ?? 0); render_header('Add activity'); ?><div class="page-head"><div><p class="eyebrow">Progress notes</p><h1>Add activity</h1></div></div><form class="form-card wide" method="post"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><label>Case<select name="case_id" required><?php $list = query($conn, $currentUser['role'] === 'student' ? 'SELECT id,case_code,client_name FROM cases WHERE assigned_student_id = ? ORDER BY case_code' : 'SELECT id,case_code,client_name FROM cases ORDER BY case_code', $currentUser['role'] === 'student' ? 'i' : '', $currentUser['role'] === 'student' ? [$currentUser['id']] : []); while ($item = $list->fetch_assoc()): ?><option value="<?= $item['id'] ?>" <?= $item['id'] === $caseId ? 'selected' : '' ?>><?= e($item['case_code'] . ' · ' . $item['client_name']) ?></option><?php endwhile; ?></select></label><div class="form-grid"><label>Activity title<input name="title" required></label><label>Date<input type="date" name="activity_date" value="<?= date('Y-m-d') ?>" required></label><label>Location<input name="location"></label><label>Responsible person<input name="responsible_person"></label><label class="span-2">Description<textarea name="description" rows="4"></textarea></label><label class="span-2">Result / output<textarea name="result_output" rows="4"></textarea></label><label class="span-2">Notes<textarea name="notes" rows="4"></textarea></label></div><button class="button primary">Save activity</button></form><?php render_footer(); exit; }

if ($page === 'report') {
	$caseId = (int) ($_GET['id'] ?? 0);
	if (!$caseId || !can_access_case($conn, $caseId, $currentUser['id'], $currentUser['role'])) { http_response_code(404); exit('Report not found.'); }
	$case = query($conn, 'SELECT * FROM cases WHERE id=?', 'i', [$caseId])->fetch_assoc();
	$study = query($conn, 'SELECT * FROM case_studies WHERE case_id=?', 'i', [$caseId])->fetch_assoc() ?: [];
	$family = query($conn, 'SELECT * FROM family_members WHERE case_id=?', 'i', [$caseId]);
	$plans = query($conn, 'SELECT * FROM treatment_plans WHERE case_id=? ORDER BY sort_order', 'i', [$caseId]);
	$formatDate = static function ($value): string { return $value ? date('F j, Y', strtotime($value)) : ''; };
	$formatIncome = static function ($value): string { return $value !== null && $value !== '' ? number_format((float) $value, 2) : ''; };
	$isReportDownload = ($_GET['download'] ?? '') === '1';
	$reportFormat = $_GET['format'] ?? 'pdf';
	if ($isReportDownload && $reportFormat === 'pdf') {
		$autoload = __DIR__ . '/vendor/autoload.php';
		if (!is_file($autoload)) { http_response_code(503); exit('PDF support is not installed.'); }
		require_once $autoload;
		ob_start();
	} elseif ($isReportDownload && $reportFormat === 'word') {
		$autoload = __DIR__ . '/vendor/autoload.php';
		if (!is_file($autoload)) { http_response_code(503); exit('Word support is not installed.'); }
		require_once $autoload;
		ob_start();
	}
	render_header('Report preview'); ?>
	<div class="print-actions"><div class="print-action-group"><button class="button primary" onclick="window.print()">Print report</button><a class="button" href="index.php?page=case-study&id=<?= $caseId ?>">Back to editor</a></div><details class="download-menu"><summary class="button report-download">Download report</summary><div class="download-menu-items"><a href="index.php?page=report&amp;id=<?= $caseId ?>&amp;download=1&amp;format=pdf">PDF <span>Best for printing</span></a><a href="index.php?page=report&amp;id=<?= $caseId ?>&amp;download=1&amp;format=word">Word <span>Editable document</span></a></div></details></div>
	<article class="report">
		<header class="report-header">
			<div class="school-seal"><img src="assets/images/psu-seal-source.png" alt="Palawan State University seal"></div>
			<div class="institution"><strong>Palawan State University</strong><span>College of Arts and Humanities</span><span>Bachelor of Science in Social Work Program</span><span>Tiniguiban Heights, Puerto Princesa City</span></div>
		</header>
		<h1>SOCIAL CASE STUDY REPORT</h1>
		<p class="report-date"><strong>Date:</strong> <u><?= e(date('F j, Y')) ?></u></p>

		<h2>I. IDENTIFYING INFORMATION</h2>
		<div class="identifying-information">
			<?php foreach (['Name'=>'client_name','Age'=>'age','Sex'=>'sex','Civil Status'=>'civil_status','Religious affiliation'=>'religious_affiliation','Date of Birth'=>'date_of_birth','Place of Birth'=>'place_of_birth','Highest Educational Attainment'=>'education','Occupation'=>'occupation','Monthly Income'=>'monthly_income','Present address'=>'present_address','Home address'=>'home_address'] as $label => $key): ?>
				<div><span><?= $label ?></span><b>:</b><p><?php if ($key === 'date_of_birth'): ?><?= e($formatDate($case[$key] ?? '')) ?><?php elseif ($key === 'monthly_income'): ?><?= e($formatIncome($case[$key] ?? '')) ?><?php else: ?><?= e((string) ($case[$key] ?? '')) ?><?php endif; ?></p></div>
			<?php endforeach; ?>
		</div>

		<h2>II. FAMILY COMPOSITION</h2>
		<table class="report-table family-table"><thead><tr><th>Name</th><th>Relationship<br>to client</th><th>Age</th><th>Birthday</th><th>Education</th><th>Occupation</th><th>Civil<br>Status</th><th>Monthly<br>Income</th></tr></thead><tbody><?php while ($row = $family->fetch_assoc()): ?><tr><td><?= nl2br(e($row['name'])) ?></td><td><?= nl2br(e($row['relationship'])) ?></td><td><?= e((string) $row['age']) ?></td><td><?= e($formatDate($row['birthday'])) ?></td><td><?= nl2br(e($row['education'])) ?></td><td><?= nl2br(e($row['occupation'])) ?></td><td><?= nl2br(e($row['civil_status'])) ?></td><td><?= e($formatIncome($row['monthly_income'])) ?></td></tr><?php endwhile; ?></tbody></table>

		<h2>III. PRESENTING PROBLEM</h2>
		<p><?= nl2br(e($study['presenting_problem'] ?? '')) ?></p>

		<h2>IV. BACKGROUND INFORMATION</h2>
		<h3>A. The Client</h3><p><?= nl2br(e($study['background_client'] ?? '')) ?></p>
		<h3>B. The Family</h3><p><?= nl2br(e($study['background_family'] ?? '')) ?></p>
		<h3>C. The Environment</h3><p><?= nl2br(e($study['background_environment'] ?? '')) ?></p>

		<h2>V. ASSESSMENT STATEMENT</h2>
		<p><?= nl2br(e($study['assessment'] ?? '')) ?></p>

		<h2>VI. TREATMENT PLAN</h2>
		<p><strong>Goal:</strong> <?= nl2br(e($study['goal'] ?? '')) ?></p>
		<table class="report-table treatment-table"><thead><tr><th>Problem/s</th><th>Objective/s</th><th>Activities</th><th>Responsible</th><th>Time frame</th><th>Expected output</th></tr></thead><tbody><?php while ($row = $plans->fetch_assoc()): ?><tr><td><?= nl2br(e($row['problems'])) ?></td><td><?= nl2br(e($row['objectives'])) ?></td><td><?= nl2br(e($row['activities'])) ?></td><td><?= nl2br(e($row['responsible_person'])) ?></td><td><?= nl2br(e($row['time_frame'])) ?></td><td><?= nl2br(e($row['expected_output'])) ?></td></tr><?php endwhile; ?></tbody></table>

		<h2>VII. EVALUATION AND RECOMMENDATION</h2>
		<p><?= nl2br(e($study['evaluation_recommendation'] ?? '')) ?></p>
		<div class="signature-grid"><div><p><strong>Prepared by:</strong></p><p class="signature-name"><u><?= e($study['prepared_signature'] ?: ($study['prepared_by'] ?? '')) ?></u><br><strong>Name &amp; Signature of SW</strong></p></div><div><p><strong>Noted by:</strong></p><p class="signature-name"><u><?= e($study['noted_signature'] ?: ($study['noted_by'] ?? '')) ?></u><br><strong>Subject Instructor</strong></p></div></div>
	</article><?php render_footer(); if ($isReportDownload) {
		$html = ob_get_clean();
		$downloadName = preg_replace('/[^A-Za-z0-9_-]+/', '-', (string) $case['case_code']) . '-case-study';
		$document = new DOMDocument();
		@$document->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
		$report = $document->getElementsByTagName('article')->item(0);
		$reportMarkup = $report ? $document->saveHTML($report) : $html;
		$stylesheet = file_get_contents(__DIR__ . '/assets/css/app.css');
		$sealPath = __DIR__ . '/assets/images/psu-seal-source.png';
		$sealMarkup = '<img src="assets/images/psu-seal-source.png" alt="Palawan State University seal">';
		if (is_file($sealPath)) {
			$sealMarkup = '<img src="data:image/png;base64,' . base64_encode((string) file_get_contents($sealPath)) . '" alt="Palawan State University seal">';
		}
		$html = '<!doctype html><html><head><meta charset="UTF-8"><style>' . $stylesheet . '</style></head><body>' . $reportMarkup . '</body></html>';
		if ($reportFormat === 'word') {
			$word = new PhpOffice\PhpWord\PhpWord();
			$word->setDefaultFontName('Times New Roman');
			$word->setDefaultFontSize(12);
			$section = $word->addSection(['pageSizeW' => 12240, 'pageSizeH' => 15840, 'marginTop' => 1080, 'marginRight' => 1080, 'marginBottom' => 1080, 'marginLeft' => 1080]);
			$center = ['alignment' => PhpOffice\PhpWord\SimpleType\Jc::CENTER];
			$right = ['alignment' => PhpOffice\PhpWord\SimpleType\Jc::RIGHT];
			$bold = ['bold' => true];
			$headerTable = $section->addTable(['borderSize' => 0, 'cellMargin' => 0]);
			$headerTable->addRow();
			$logoCell = $headerTable->addCell(1800);
			if (is_file($sealPath)) { $logoCell->addImage($sealPath, ['width' => 76, 'height' => 99, 'alignment' => PhpOffice\PhpWord\SimpleType\Jc::CENTER]); }
			$institutionCell = $headerTable->addCell(7560);
			$institutionCell->addText('Palawan State University', ['bold' => true, 'size' => 14], $center);
			$institutionCell->addText('College of Arts and Humanities', null, $center);
			$institutionCell->addText('Bachelor of Science in Social Work Program', null, $center);
			$institutionCell->addText('Tiniguiban Heights, Puerto Princesa City', null, $center);
			$section->addTextBreak(1);
			$section->addText('SOCIAL CASE STUDY REPORT', ['bold' => true, 'size' => 14], $center);
			$section->addText('Date: ' . date('F j, Y'), ['bold' => true, 'underline' => 'single'], $right);
			$section->addTextBreak(1);
			$section->addText('I. IDENTIFYING INFORMATION', $bold);
			$detailsTable = $section->addTable(['borderSize' => 0, 'cellMargin' => 0]);
			foreach (['Name' => (string) $case['client_name'], 'Age' => (string) ($case['age'] ?? ''), 'Sex' => (string) ($case['sex'] ?? ''), 'Civil Status' => (string) ($case['civil_status'] ?? ''), 'Religious affiliation' => (string) ($case['religious_affiliation'] ?? ''), 'Date of Birth' => $formatDate($case['date_of_birth'] ?? ''), 'Place of Birth' => (string) ($case['place_of_birth'] ?? ''), 'Highest Educational Attainment' => (string) ($case['education'] ?? ''), 'Occupation' => (string) ($case['occupation'] ?? ''), 'Monthly Income' => $formatIncome($case['monthly_income'] ?? ''), 'Present address' => (string) ($case['present_address'] ?? ''), 'Home address' => (string) ($case['home_address'] ?? '')] as $label => $value) {
				$detailsTable->addRow();
				$detailsTable->addCell(3000)->addText($label);
				$detailsTable->addCell(300)->addText(':');
				$detailsTable->addCell(6060)->addText($value);
			}
			$section->addTextBreak(1);
			$section->addText('II. FAMILY COMPOSITION', $bold);
			$familyTable = $section->addTable(['borderSize' => 6, 'borderColor' => '000000', 'cellMargin' => 60]);
			$familyHeaders = ['Name', 'Relationship to client', 'Age', 'Birthday', 'Education', 'Occupation', 'Civil Status', 'Monthly Income'];
			$familyTable->addRow();
			foreach ($familyHeaders as $header) { $familyTable->addCell()->addText($header, $bold, $center); }
			while ($row = $family->fetch_assoc()) {
				$familyTable->addRow();
				foreach ([(string) $row['name'], (string) $row['relationship'], (string) $row['age'], $formatDate($row['birthday']), (string) $row['education'], (string) $row['occupation'], (string) $row['civil_status'], $formatIncome($row['monthly_income'])] as $value) { $familyTable->addCell()->addText($value); }
			}
			$section->addTextBreak(1);
			foreach ([['III. PRESENTING PROBLEM', $study['presenting_problem'] ?? ''], ['IV. BACKGROUND INFORMATION - A. The Client', $study['background_client'] ?? ''], ['IV. BACKGROUND INFORMATION - B. The Family', $study['background_family'] ?? ''], ['IV. BACKGROUND INFORMATION - C. The Environment', $study['background_environment'] ?? ''], ['V. ASSESSMENT STATEMENT', $study['assessment'] ?? '']] as [$heading, $content]) {
				$section->addText($heading, $bold);
				$section->addText((string) $content);
				$section->addTextBreak(1);
			}
			$section->addText('VI. TREATMENT PLAN', $bold);
			$section->addText('Goal: ' . (string) ($study['goal'] ?? ''));
			$planTable = $section->addTable(['borderSize' => 6, 'borderColor' => '000000', 'cellMargin' => 60]);
			$planTable->addRow();
			foreach (['Problem/s', 'Objective/s', 'Activities', 'Responsible', 'Time frame', 'Expected output'] as $header) { $planTable->addCell()->addText($header, $bold, $center); }
			while ($row = $plans->fetch_assoc()) {
				$planTable->addRow();
				foreach ([(string) $row['problems'], (string) $row['objectives'], (string) $row['activities'], (string) $row['responsible_person'], (string) $row['time_frame'], (string) $row['expected_output']] as $value) { $planTable->addCell()->addText($value); }
			}
			$section->addTextBreak(1);
			$section->addText('VII. EVALUATION AND RECOMMENDATION', $bold);
			$section->addText((string) ($study['evaluation_recommendation'] ?? ''));
			$section->addTextBreak(2);
			$signatureTable = $section->addTable(['borderSize' => 0]);
			$signatureTable->addRow();
			$signatureTable->addCell(4680)->addText("Prepared by:\n" . ($study['prepared_signature'] ?: ($study['prepared_by'] ?? '')) . "\nName & Signature of SW");
			$signatureTable->addCell(4680)->addText("Noted by:\n" . ($study['noted_signature'] ?: ($study['noted_by'] ?? '')) . "\nSubject Instructor");
			$tempDocx = tempnam(sys_get_temp_dir(), 'swassist-docx-');
			$writer = PhpOffice\PhpWord\IOFactory::createWriter($word, 'Word2007');
			$writer->save($tempDocx);
			header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
			header('Content-Disposition: attachment; filename="' . $downloadName . '.docx"');
			header('Content-Length: ' . filesize($tempDocx));
			readfile($tempDocx);
			unlink($tempDocx);
			exit;
		}
		if (!extension_loaded('gd')) {
			$html = str_replace([$sealMarkup, '<img src="assets/images/psu-seal-source.png" alt="Palawan State University seal">'], '', $html);
		} else {
			$html = str_replace('<img src="assets/images/psu-seal-source.png" alt="Palawan State University seal">', $sealMarkup, $html);
		}
		$dompdfTempDir = sys_get_temp_dir() . '/swassist-dompdf';
		if (!is_dir($dompdfTempDir)) { mkdir($dompdfTempDir, 0700, true); }
		$options = new Dompdf\Options();
		$options->set('defaultFont', 'Times New Roman');
		$options->set('isRemoteEnabled', true);
		$options->set('fontDir', $dompdfTempDir);
		$options->set('fontCache', $dompdfTempDir);
		$options->set('tempDir', $dompdfTempDir);
		$options->setChroot(__DIR__);
		$dompdf = new Dompdf\Dompdf($options);
		$dompdf->loadHtml($html, 'UTF-8');
		$dompdf->setPaper('letter', 'portrait');
		$dompdf->render();
		$dompdf->stream($downloadName . '.pdf', ['Attachment' => true]);
		exit;
	} exit; }

if ($page === 'qr') { render_header('QR utility'); ?><div class="page-head"><div><p class="eyebrow">Survey tools</p><h1>QR code generator</h1><p class="muted">Generate a scannable code for a Google Form or survey link. URLs are not stored.</p></div></div><form class="form-card qr-form" onsubmit="return makeQr(event)"><label>URL<input id="qr-url" type="url" placeholder="https://forms.google.com/..." required></label><label>Title / description<input id="qr-title"></label><button class="button primary">Generate QR code</button><div id="qr-result" class="qr-result" hidden><h2 id="qr-label"></h2><img id="qr-image" alt="Generated QR code"><a id="qr-download" class="button" download="survey-qr.png">Download</a></div></form><?php render_footer(); exit; }

render_header('Dashboard'); $scope = $currentUser['role'] === 'student' ? 'WHERE assigned_student_id = ' . (int)$currentUser['id'] : ''; $total = query($conn, "SELECT COUNT(*) n FROM cases $scope")->fetch_assoc()['n']; $active = query($conn, "SELECT COUNT(*) n FROM cases $scope " . ($scope ? 'AND' : 'WHERE') . " status='Active'")->fetch_assoc()['n']; $completed = query($conn, "SELECT COUNT(*) n FROM cases $scope " . ($scope ? 'AND' : 'WHERE') . " status='Completed'")->fetch_assoc()['n']; $recent = query($conn, "SELECT id,case_code,client_name,updated_at FROM cases $scope ORDER BY updated_at DESC LIMIT 5"); ?><div class="page-head"><div><p class="eyebrow">Workspace overview</p><h1>Good day, <?= e(explode(' ', $currentUser['full_name'])[0]) ?>.</h1><p class="muted">A clear view of the records needing your attention.</p></div><a class="button primary" href="index.php?page=case-create">+ New case</a></div><div class="stats"><div><strong><?= $total ?></strong><span>Total cases</span></div><div><strong><?= $active ?></strong><span>Active</span></div><div><strong><?= $completed ?></strong><span>Completed</span></div></div><section class="panel"><div class="section-head"><h2>Recently updated</h2><a class="text-link" href="index.php?page=cases">View all cases</a></div><div class="recent-list"><?php while ($item=$recent->fetch_assoc()): ?><a href="index.php?page=case&id=<?= $item['id'] ?>"><span><strong><?= e($item['case_code']) ?></strong> <?= e($item['client_name']) ?></span><time><?= e(date('M j, Y', strtotime($item['updated_at']))) ?></time></a><?php endwhile; ?></div></section><?php render_footer();
