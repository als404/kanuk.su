<?php 
require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog.php");

global $USER;
if($USER->IsAdmin()) {
    require_once('core.php');
?>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.1/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-+0n0xVW2eSR5OomGNYDnhzAbDsOXxcvSN1TPprVMTNDbiYZCxYbOOl7+AMvyTG2x" crossorigin="anonymous">
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/font-awesome/4.5.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="style/main.css">

    
            <div class="card">
                <div class="card-header">
                    Обновление цен и наличия на складе в БД из файла CSV
                </div>
                <div class="card-body">
                    <h5 class="card-title">Внимание!</h5>
                    <p class="card-text">Структура файла должна быть в соответствии со спецификацией, артикул должен быть заполнен обязательно, остальные поля, если содержат пустое значение либо 0 либо другое значение не являющееся целым числом либо float числом будут означать 0. </p>
                    <p>Если вы собираетесь обновить наличие товара на складе, добавьте эти данные в CVS файл в соответствии с примером и установите кнопку "Учитывать наличие" в активное положение!</p>
                    <div class="alert alert-info mt-3">пример оформления файла CSV: { артикул;стоимость;кол-во; }</div>
                    <?php require_once(BASE .'/inc/form.php'); ?>

                    <div class="alert alert-warning mt-3">
                        <p>После обновления цен требуется обязательно сбросить весь кеш сайта, иначе будут показываться старые цены!!!</p>
                        <p><small>Перейдите по ссылке <a href="https://discount-tools.ru/bitrix/admin/cache.php?lang=ru">"Очистка файлов кеша"</a> на страницу "Настройки кеширования" на вкладке "Очистистка файлов кеша" выбирете пункт "Все" и нажмите кнопку "Начать". Дождитесть окончания работы по удалению кеша.</small></p>
                    </div>
                    <div class="alert alert-success mt-3" role="alert" style="display:none"></div>
                    <div class="alert alert-danger mt-3" role="alert" style="display:none"></div>
                </div>
            </div>

    <div class="align-self-end">
        <div class="container mt-3">&copy; 2016-<?php echo date('Y'); ?> All rights reserved. www.qwedy.com</div>
    </div>

    <script src="js/main.js"></script>

<?php 
} else {
?>
    <style>
        .auth .kabinet a.login_anch span, .kabinet a.personal span {color:#555;}
        .auth .kabinet .register {display:none;}
    </style>
    <div class="auth"><h2>Вы не авторизированны, либо у вас нет прав для просмотра данной страницы!</h2>
<?
    define("NEED_AUTH", true);
    $APPLICATION->IncludeComponent("bitrix:main.include", "", array("AREA_FILE_SHOW" => "file", "PATH" => SITE_DIR."include/registration.php"), false);
?>
    </div>
<?
} 

if(defined("B_PROLOG_INCLUDED") && B_PROLOG_INCLUDED===true) { 
   require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog.php"); 
}
?>