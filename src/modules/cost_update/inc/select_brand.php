<?php 
    $data['sql']['query'] = 'SELECT ID, NAME, CODE FROM b_iblock_element WHERE IBLOCK_ID = 14 AND ACTIVE = "Y" ORDER BY NAME';
    foreach($query->get($data) as $q){
        echo '<option value="'.$q->ID.'" data-code="'.$q->CODE.'">'.$q->NAME.'</option>';
    }
