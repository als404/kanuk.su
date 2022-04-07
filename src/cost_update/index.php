<?php 
require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog.php");
require_once('core.php'); 
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Обновление стоимости товаров</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.1/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-+0n0xVW2eSR5OomGNYDnhzAbDsOXxcvSN1TPprVMTNDbiYZCxYbOOl7+AMvyTG2x" crossorigin="anonymous">
    <?php include_once(BASE .'/inc/favicon.inc.php'); ?>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/font-awesome/4.5.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="style/main.css">
</head>
<body>
    <div class="content">
        <div class="container">
            <div class="card">
                <div class="card-header">
                    Обновление цен и наличия на складе в БД из файла CSV
                </div>
                <div class="card-body">
                    <h5 class="card-title">Внимание!</h5>
                    <p class="card-text">Структура файла должна быть в соответствии со спецификацией, пример: { артикул;стоимость;кол-во; }, артикул должен быть заполнен обязательно, остальные поля, если содержат пустое либо 0 либо другое значение не являющееся целым либо float числом будут означать 0.</p>
                    <?php require_once(BASE .'/inc/form.php'); ?>
                    <div class="alert alert-success mt-3" role="alert" style="display:none"></div>
                    <div class="alert alert-danger mt-3" role="alert" style="display:none"></div>
                </div>
            </div>
        </div>
    </div>

    <footer class="align-self-end">
        <div class="container mt-3">&copy; 2016-<?php echo date('Y'); ?> All rights reserved. www.qwedy.com</div>
    </footer>

    <script src="js/main.js"></script>
</body>
</html>
<?
if(defined("B_PROLOG_INCLUDED") && B_PROLOG_INCLUDED===true)
{
	require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog.php");
}
?>