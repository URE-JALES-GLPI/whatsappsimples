<?php

include("../../../inc/includes.php");

Session::checkRight("profile", UPDATE);

if (isset($_POST['update'])) {
    $profiles_id = (int)$_POST['profiles_id'];
    $rights      = $_POST['rights'] ?? [];

    if ($profiles_id > 0) {
        global $DB;
        $profileRight = new ProfileRight();
        
        foreach ($rights as $rightName => $rightValue) {
            $value = (int)$rightValue;
            
            $existing = $profileRight->find([
                'profiles_id' => $profiles_id,
                'name'        => $rightName
            ]);
            
            if (count($existing)) {
                $id = array_key_first($existing);
                $profileRight->update(['id' => $id, 'rights' => $value]);
            } else {
                $profileRight->add([
                    'profiles_id' => $profiles_id,
                    'name'        => $rightName,
                    'rights'      => $value
                ]);
            }
        }
    }

    Html::back();
} else {
    Html::displayErrorAndDie('Lost');
}
