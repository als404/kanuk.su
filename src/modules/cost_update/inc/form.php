<form id="formCostUpdate" method="POST" action="" class="form-horizontal mt-5 mb-5" enctype="multipart/form-data">
    <div class="row">
        <div class="col-12">
            <select class="form-select form-select-lg select_brand" aria-label=".form-select-lg">
                <option selected>Выбор бренда...</option>
                <?php
                    $data['sql']['query'] = 'SELECT ID, NAME, CODE FROM b_iblock_element WHERE IBLOCK_ID = 14 AND ACTIVE = "Y" ORDER BY NAME';
                    foreach($query->get($data) as $q){
                        echo '<option value="'.$q->ID.'" data-code="'.$q->NAME.'">'.$q->CODE.'</option>';
                    }
                ?>
            </select>
        </div>
        <div class="col-12 mt-3">
            <div class="form-file">
                <div class="form-group">
                    <input type="file" name="file" id="file" class="input-file">
                    <label for="file" class="btn btn-tertiary js-labelFile">
                        <i class="icon fa fa-check"></i>
                        <span class="js-fileName">Загрузить файл</span>
                    </label>
                </div>
            </div>
        </div>
        <div class="mt-3 d-flex justify-content-center">
            <div class="form-check form-switch d-flex align-items-center">
                <input class="form-check-input" type="checkbox" id="flexSwitchCheckDefault">
                <label class="form-check-label" for="flexSwitchCheckDefault">Учитывать наличие</label>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-12 mt-3">
            <button type="submit" class="btn btn-outline-dark btn_update">Обновить стоимость</button>
        </div>
    </div>
</form>