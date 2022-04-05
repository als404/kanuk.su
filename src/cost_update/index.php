<?php
session_start();
define('BASE', $_SERVER['DOCUMENT_ROOT']);
define('DIR', __DIR__);
// echo '<pre>'.print_r($_SERVER,1).'</pre>';
// echo __DIR__;
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Обновление стоимости товаров</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.1/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-+0n0xVW2eSR5OomGNYDnhzAbDsOXxcvSN1TPprVMTNDbiYZCxYbOOl7+AMvyTG2x" crossorigin="anonymous">
    <?php include_once(DIR .'/tmpl/favicon.inc.php'); ?>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/font-awesome/4.5.0/css/font-awesome.min.css">
</head>
<body>
    <style>
        body {
            min-width: 362px;
        }
        .content {
            margin-top: 1em;
        }
        .row > div {
            text-align:center;
        }
        .form-file input {
            border: 1px solid #ccc;
            width: 100%;
            height: 38px;
        }
        .form-file .btn-tertiary{color:#555;padding:0;line-height:44px;width:300px;margin:auto;display:block;border:2px solid #555}
        .form-file .btn-tertiary:hover,.form-file .btn-tertiary:focus{color:#888;border-color:#888}
        .form-file .input-file{width:.1px;height:.1px;opacity:0;overflow:hidden;position:absolute;z-index:-1}
        .form-file .input-file + .js-labelFile{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;padding:0 10px;cursor:pointer}
        .form-file .input-file + .js-labelFile .icon:before{content:"\f093"}
        .form-file .input-file + .js-labelFile.has-file .icon:before{content:"\f00c";color:#5AAC7B}

    </style>
    <div class="content">
        <div class="container">
            <div class="card">
                <div class="card-header">
                    Обновление цен и наличия на складе в БД из файла CSV
                </div>
                <div class="card-body">
                    <h5 class="card-title">Внимание!</h5>
                    <p class="card-text">Структура файла должна быть в соответствии со спецификацией, пример: { артикул;стоимость;акция;кол-во; }, артикул должен быть заполнен обязательно, остальные поля, если содержат пустое либо 0 либо другое значение не являющееся целым либо float числом будут означать 0.</p>
                    <div class="row">
                        <div class="col-12 col-md-6 col-lg-7 col-xl-8 col-xxl-9">
                            <select class="form-select form-select-lg select_brand" aria-label=".form-select-lg">
                                <option selected>Выбор бренда...</option>
                                <?php include_once(DIR . "/tmpl/select_brand.php"); ?>
                            </select>
                        </div>
                        <div class="col-12 col-md-6 col-lg-5 col-xl-4 col-xxl-3 mt-3 mt-md-0">
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
                    </div>
                    <div class="row">
                        <div class="col-12 mt-3">
                            <button type="button" class="btn btn-outline-dark btn_update">Обновить стоимость</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <footer class="align-self-end">
        <div class="container mt-3">
            &copy; 2016-<?php echo date('Y'); ?> All rights reserved. www.qwedy.com 
        </div>
    </footer>

    <script src="js/main.js"></script>
   <!-- <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.1/dist/js/bootstrap.bundle.min.js" integrity="sha384-gtEjrD/SeCtmISkJkNUaaKMoLD0//ElJ19smozuHV6z3Iehds+3Ulb9Bn9Plx0x4" crossorigin="anonymous"></script> -->
</body>
</html>