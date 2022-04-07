<form id="formCostUpdate" method="POST" action="" class="form-horizontal" enctype="multipart/form-data">
    <div class="row">
        <div class="col-12 col-md-5 col-lg-6 col-xl-7 col-xxl-7">
            <select class="form-select form-select-lg select_brand" aria-label=".form-select-lg">
                <option selected>Выбор бренда...</option>
                <?php include_once(BASE . "/inc/select_brand.php"); ?>
            </select>
        </div>
        <div class="col-12 col-md-4 col-lg-4 col-xl-3 col-xxl-3 mt-3 mt-md-0">
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
        <div class="col-md-3 col-lg-2 col-xl-2 col-xxl-2 mt-3 mt-md-0 d-flex justify-content-center">
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