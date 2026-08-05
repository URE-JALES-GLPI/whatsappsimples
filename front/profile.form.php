<?php
include('../../../inc/includes.php');
Session::checkRight('profile', UPDATE);

if (isset($_POST['update'])) {
    $profileRight = new ProfileRight();
    $profiles_id  = (int)$_POST['profiles_id'];
    $rights       = (int)$_POST['rights'];

    $existing = $profileRight->find([
        'profiles_id' => $profiles_id,
        'name'        => 'plugin_whatsappsimples',
    ]);

    if (count($existing)) {
        $id = array_key_first($existing);
        $profileRight->update(['id' => $id, 'rights' => $rights]);
    } else {
        $profileRight->add([
            'profiles_id' => $profiles_id,
            'name'        => 'plugin_whatsappsimples',
            'rights'      => $rights,
        ]);
    }
}

Html::back();
