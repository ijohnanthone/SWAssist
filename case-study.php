<?php
$caseId = (int) ($_GET['id'] ?? 0);
if (!$caseId || !can_access_case($conn, $caseId, $currentUser['id'], $currentUser['role'])) {
    http_response_code(404);
    exit('Case not found.');
}
$case = query($conn, 'SELECT * FROM cases WHERE id = ?', 'i', [$caseId])->fetch_assoc();
$study = query($conn, 'SELECT * FROM case_studies WHERE case_id = ?', 'i', [$caseId])->fetch_assoc() ?: [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $conn->begin_transaction();
    try {
    $fields = ['presenting_problem', 'background_client', 'background_family', 'background_environment', 'assessment', 'goal', 'evaluation_recommendation', 'prepared_by', 'prepared_signature', 'noted_by', 'noted_signature'];
    $values = array_map(static fn ($field) => trim($_POST[$field] ?? ''), $fields);
    $statement = $conn->prepare('INSERT INTO case_studies (case_id, presenting_problem, background_client, background_family, background_environment, assessment, goal, evaluation_recommendation, prepared_by, prepared_signature, noted_by, noted_signature) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE presenting_problem=VALUES(presenting_problem), background_client=VALUES(background_client), background_family=VALUES(background_family), background_environment=VALUES(background_environment), assessment=VALUES(assessment), goal=VALUES(goal), evaluation_recommendation=VALUES(evaluation_recommendation), prepared_by=VALUES(prepared_by), prepared_signature=VALUES(prepared_signature), noted_by=VALUES(noted_by), noted_signature=VALUES(noted_signature)');
    $bindValues = array_merge([$caseId], $values);
    $bindParameters = ['isssssssssss'];
    foreach ($bindValues as $index => &$value) {
        $bindParameters[] = &$value;
    }
    call_user_func_array([$statement, 'bind_param'], $bindParameters);
    unset($value);
    $statement->execute();

    query($conn, 'DELETE FROM family_members WHERE case_id = ?', 'i', [$caseId]);
    foreach ($_POST['family_name'] ?? [] as $index => $name) {
        if (trim($name) === '') {
            continue;
        }
        $familyAge = trim((string) ($_POST['family_age'][$index] ?? ''));
        $familyBirthday = trim((string) ($_POST['family_birthday'][$index] ?? ''));
        $familyIncome = optional_decimal($_POST['family_income'][$index] ?? null);
            query($conn, 'INSERT INTO family_members (case_id,name,relationship,age,birthday,education,occupation,civil_status,monthly_income) VALUES (?,?,?,NULLIF(?, \'\'),NULLIF(?, \'\'),?,?,?,?)', 'issssssss', [$caseId, trim($name), $_POST['family_relationship'][$index] ?? '', $familyAge, $familyBirthday, $_POST['family_education'][$index] ?? '', $_POST['family_occupation'][$index] ?? '', $_POST['family_civil_status'][$index] ?? '', $familyIncome]);
    }
    query($conn, 'DELETE FROM treatment_plans WHERE case_id = ?', 'i', [$caseId]);
    foreach ($_POST['plan_problem'] ?? [] as $index => $problem) {
        if (trim($problem) === '') {
            continue;
        }
        query($conn, 'INSERT INTO treatment_plans (case_id,problems,objectives,activities,responsible_person,time_frame,expected_output,sort_order) VALUES (?,?,?,?,?,?,?,?)', 'issssssi', [$caseId, trim($problem), $_POST['plan_objective'][$index] ?? '', $_POST['plan_activities'][$index] ?? '', $_POST['plan_responsible'][$index] ?? '', $_POST['plan_time'][$index] ?? '', $_POST['plan_output'][$index] ?? '', $index]);
    }
    $conn->commit();
    flash('success', 'Case study saved.');
    redirect('index.php?page=case-study&id=' . $caseId);
    } catch (Throwable $exception) {
        $conn->rollback();
        error_log('SWAssist case-study save failed: ' . $exception->getMessage());
        throw $exception;
    }
}

$familyRows = query($conn, 'SELECT * FROM family_members WHERE case_id = ? ORDER BY id', 'i', [$caseId])->fetch_all(MYSQLI_ASSOC);
$planRows = query($conn, 'SELECT * FROM treatment_plans WHERE case_id = ? ORDER BY sort_order, id', 'i', [$caseId])->fetch_all(MYSQLI_ASSOC);
render_header('Case study');
?>
<div class="page-head"><div><p class="eyebrow">Social Case Study Report</p><h1><?= e($case['client_name']) ?></h1></div><a class="button" target="_blank" href="index.php?page=report&id=<?= $caseId ?>">Preview / print</a></div>
<form method="post" class="study-form"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
<section class="panel"><h2>I. Identifying information</h2><p class="muted">This section is populated from the case record. <a href="index.php?page=case&id=<?= $caseId ?>">View case details</a></p></section>
<section class="panel"><h2>II. Family composition</h2><div id="family-rows"><?php foreach ($familyRows ?: [[]] as $row): ?><div class="repeat-row family-row"><input name="family_name[]" placeholder="Name" value="<?= e($row['name'] ?? '') ?>"><input name="family_relationship[]" placeholder="Relationship" value="<?= e($row['relationship'] ?? '') ?>"><input name="family_age[]" type="number" placeholder="Age" value="<?= e((string)($row['age'] ?? '')) ?>"><input name="family_birthday[]" type="date" value="<?= e($row['birthday'] ?? '') ?>"><input name="family_education[]" placeholder="Educational" value="<?= e($row['education'] ?? '') ?>"><input name="family_occupation[]" placeholder="Occupation" value="<?= e($row['occupation'] ?? '') ?>"><input name="family_civil_status[]" placeholder="Civil status" value="<?= e($row['civil_status'] ?? '') ?>"><input name="family_income[]" placeholder="Income" value="<?= e((string)($row['monthly_income'] ?? '')) ?>"><button type="button" class="icon-button remove-row">Remove</button></div><?php endforeach; ?></div><button type="button" class="button" data-add="family">+ Add family member</button></section>
<section class="panel"><h2>III. Presenting problem</h2><textarea name="presenting_problem" rows="6"><?= e($study['presenting_problem'] ?? '') ?></textarea></section>
<section class="panel"><h2>IV. Background information</h2><label>A. The Client<textarea name="background_client" rows="5"><?= e($study['background_client'] ?? '') ?></textarea></label><label>B. The Family<textarea name="background_family" rows="5"><?= e($study['background_family'] ?? '') ?></textarea></label><label>C. The Environment<textarea name="background_environment" rows="5"><?= e($study['background_environment'] ?? '') ?></textarea></label></section>
<section class="panel"><h2>V. Assessment statement</h2><textarea name="assessment" rows="7"><?= e($study['assessment'] ?? '') ?></textarea></section>
<section class="panel"><h2>VI. Treatment plan</h2><label>Goal<textarea name="goal" rows="3"><?= e($study['goal'] ?? '') ?></textarea></label><div id="plan-rows"><?php foreach ($planRows ?: [[]] as $row): ?><div class="repeat-row plan-row"><textarea name="plan_problem[]" placeholder="Problem/s"><?= e($row['problems'] ?? '') ?></textarea><textarea name="plan_objective[]" placeholder="Objective/s"><?= e($row['objectives'] ?? '') ?></textarea><textarea name="plan_activities[]" placeholder="Activities"><?= e($row['activities'] ?? '') ?></textarea><input name="plan_responsible[]" placeholder="Responsible person" value="<?= e($row['responsible_person'] ?? '') ?>"><input name="plan_time[]" placeholder="Time frame" value="<?= e($row['time_frame'] ?? '') ?>"><textarea name="plan_output[]" placeholder="Expected output"><?= e($row['expected_output'] ?? '') ?></textarea><button type="button" class="icon-button remove-row">Remove</button></div><?php endforeach; ?></div><button type="button" class="button" data-add="plan">+ Add treatment row</button></section>
<section class="panel"><h2>VII. Evaluation and recommendation</h2><textarea name="evaluation_recommendation" rows="7"><?= e($study['evaluation_recommendation'] ?? '') ?></textarea><div class="form-grid"><label>Prepared by<input name="prepared_by" value="<?= e($study['prepared_by'] ?? $currentUser['full_name']) ?>"></label><label>Signature / attachment<input name="prepared_signature" value="<?= e($study['prepared_signature'] ?? '') ?>"></label><label>Noted by / Subject Instructor<input name="noted_by" value="<?= e($study['noted_by'] ?? '') ?>"></label><label>Signature / attachment<input name="noted_signature" value="<?= e($study['noted_signature'] ?? '') ?>"></label></div></section><button class="button primary" type="submit">Save case study</button></form>
<?php render_footer();