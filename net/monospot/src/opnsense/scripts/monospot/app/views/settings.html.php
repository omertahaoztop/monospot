<h2>Ayarlar</h2>

<? if (!empty($errors)): ?>
    <ul class="errors">
        <? foreach($errors as $error): ?>
        <li><?=$error?>
            <? endforeach; ?>
    </ul>
<? endif; ?>

<div class="settings-pane">
    <form action="<?=url_for('settings')?>" method="POST" enctype="multipart/form-data">
        <? include 'settings_form_inner.html.php'; ?>
    </form>
</div>
