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
                    ���������� ��� � ������� �� ������ � �� �� ����� CSV
                </div>
                <div class="card-body">
                    <h5 class="card-title">��������!</h5>
                    <p class="card-text">��������� ����� ������ ���� � ������������ �� �������������, ������� ������ ���� �������� �����������, ��������� ����, ���� �������� ������ �������� ���� 0 ���� ������ �������� �� ���������� ����� ������ ���� float ������ ����� �������� 0. </p>
                    <p>���� �� ����������� �������� ������� ������ �� ������, �������� ��� ������ � CVS ���� � ������������ � �������� � ���������� ������ "��������� �������" � �������� ���������!</p>
                    <div class="alert alert-info mt-3">������ ���������� ����� CSV: { �������;���������;���-��; }</div>
                    <?php require_once(BASE .'/inc/form.php'); ?>

                    <div class="alert alert-warning mt-3">
                        <p>����� ���������� ��� ��������� ����������� �������� ���� ��� �����, ����� ����� ������������ ������ ����!!!</p>
                        <p><small>��������� �� ������ <a href="https://discount-tools.ru/bitrix/admin/cache.php?lang=ru">"������� ������ ����"</a> �� �������� "��������� �����������" �� ������� "���������� ������ ����" �������� ����� "���" � ������� ������ "������". ���������� ��������� ������ �� �������� ����.</small></p>
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
    <div class="auth"><h2>�� �� ���������������, ���� � ��� ��� ���� ��� ��������� ������ ��������!</h2>
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