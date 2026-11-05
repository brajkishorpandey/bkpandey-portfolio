<?php
declare(strict_types=1);
$values=bp_site_values($db);
$resumeLimit=bp_resume_upload_limit();
$uploadAvailable=(bool)ini_get('file_uploads') && $resumeLimit>0;
$resumeSizeLabel=$resumeLimit>=1024*1024
    ? rtrim(rtrim(number_format(floor($resumeLimit*10/(1024*1024))/10,1,'.',''),'0'),'.').' MB'
    : number_format((int)floor($resumeLimit/1024)).' KB';
?>
<section class="admin-section traffic-panel" id="resume-upload" aria-labelledby="resume-upload-title">
    <h2 id="resume-upload-title">Résumé PDF</h2>
    <p>Replace the PDF visitors download from the website.</p>
    <p><a class="btn" href="/resume/download">Download current résumé ↓</a></p>
    <?php if ($uploadAvailable): ?>
    <form method="post" action="/admin?site=1#resume-upload" enctype="multipart/form-data" class="admin-editor">
        <input type="hidden" name="csrf" value="<?= bp_h(bp_csrf()) ?>">
        <input type="hidden" name="action" value="site_resume">
        <input type="hidden" name="MAX_FILE_SIZE" value="<?= $resumeLimit ?>">
        <div class="field">
            <label for="resume-pdf">Choose your updated résumé</label>
            <input id="resume-pdf" name="resume" type="file" accept=".pdf,application/pdf" aria-describedby="resume-limit" required>
            <small id="resume-limit">PDF only. Maximum <?= bp_h($resumeSizeLabel) ?>.</small>
        </div>
        <button type="submit" class="btn btn-primary">Upload résumé PDF</button>
    </form>
    <?php else: ?>
        <p class="admin-notice admin-error">Hosting has disabled file uploads or set the limit too low.</p>
    <?php endif; ?>
</section>
<?php
echo '<form method="post" enctype="multipart/form-data" class="admin-editor"><input type="hidden" name="csrf" value="'.bp_h(bp_csrf()).'"><input type="hidden" name="action" value="site"><h2>Your website.</h2><p class="admin-subtitle">Edit your introduction, brief résumé, public contact details, and enquiry email text. The portrait upload is below. Saving refreshes the public cache.</p>';
foreach(bp_site_fields() as $key=>[$label,$default,$max,$type]){
    $v=$values[$key]??$default;$long=$type==='text';
    echo '<div class="field"><label for="site-'.bp_h($key).'">'.bp_h($label).'</label>';
    if($long)echo '<textarea class="excerpt-input" id="site-'.bp_h($key).'" name="'.bp_h($key).'" required maxlength="'.$max.'">'.bp_h($v).'</textarea>';
    else echo '<input id="site-'.bp_h($key).'" name="'.bp_h($key).'" type="'.($type==='email'?'email':($type==='url'?'url':'text')).'" required maxlength="'.$max.'" value="'.bp_h($v).'">';
    if(in_array($key,['email_subject','email_body'],true))echo '<small>Available placeholders: {name}, {email}, {service}, {message}. Visitors review the prepared email in their email app.</small>';
    echo '</div>';
}
echo '<div class="editor-grid"><div class="field"><label for="profile-upload">Profile picture — JPEG, PNG, WebP; up to 10 MB</label><img class="admin-settings-preview" src="/media/profile" alt="Current profile picture"><input id="profile-upload" name="profile" type="file" accept="image/jpeg,image/png,image/webp"></div></div><button class="btn btn-primary" type="submit">Save website changes ↗</button></form><div class="admin-section"><h2>Public cache.</h2><p>Saving a post or website setting refreshes the cache automatically.</p><form method="post"><input type="hidden" name="csrf" value="'.bp_h(bp_csrf()).'"><button class="btn" name="action" value="cache">Refresh public cache</button></form></div>';
