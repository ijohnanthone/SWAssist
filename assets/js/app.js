document.addEventListener('click', (event) => {
    const visitSummary = event.target.closest('.timeline-item > summary');
    if (visitSummary) {
        const editLink = visitSummary.parentElement.querySelector('.activity-edit-link');
        if (editLink) {
            event.preventDefault();
            const detailUrl = new URL(editLink.href);
            detailUrl.searchParams.set('page', 'activity-view');
            window.location.href = detailUrl.toString();
            return;
        }
    }
    if (event.target.matches('.remove-row')) {
        const row = event.target.closest('.repeat-row');
        if (row.parentElement.children.length > 1) row.remove();
    }
    if (event.target.matches('[data-add="family"]')) addFamilyRow();
    if (event.target.matches('[data-add="plan"]')) addPlanRow();
});
document.addEventListener('submit', (event) => {
    if (event.target.matches('.qr-form')) {
        makeQr(event);
    }
    if (event.target.matches('.delete-account-form')) {
        if (!confirm('Remove this account?')) {
            event.preventDefault();
        }
    }
});
function addFamilyRow(){document.querySelector('#family-rows').insertAdjacentHTML('beforeend','<div class="repeat-row family-row"><input name="family_name[]" placeholder="Name"><input name="family_relationship[]" placeholder="Relationship"><input name="family_age[]" type="number" placeholder="Age"><input name="family_birthday[]" type="date"><input name="family_education[]" placeholder="Educational"><input name="family_occupation[]" placeholder="Occupation"><input name="family_civil_status[]" placeholder="Civil status"><input name="family_income[]" placeholder="Income"><button type="button" class="icon-button remove-row">Remove</button></div>')}
function addPlanRow(){document.querySelector('#plan-rows').insertAdjacentHTML('beforeend','<div class="repeat-row plan-row"><textarea name="plan_problem[]" placeholder="Problem/s"></textarea><textarea name="plan_objective[]" placeholder="Objective/s"></textarea><textarea name="plan_activities[]" placeholder="Activities"></textarea><input name="plan_responsible[]" placeholder="Responsible person"><input name="plan_time[]" placeholder="Time frame"><textarea name="plan_output[]" placeholder="Expected output"></textarea><button type="button" class="icon-button remove-row">Remove</button></div>')}
function makeQr(event){event.preventDefault();const url=document.querySelector('#qr-url').value,title=document.querySelector('#qr-title').value||'Survey QR code',imageUrl='https://api.qrserver.com/v1/create-qr-code/?size=300x300&data='+encodeURIComponent(url);document.querySelector('#qr-label').textContent=title;document.querySelector('#qr-image').src=imageUrl;document.querySelector('#qr-download').href=imageUrl;document.querySelector('#qr-result').hidden=false;return false}