<?php
/** Local Bitrix module installer. Does not create/delete module data tables. */
class dt_catalogproperties extends CModule
{
    public $MODULE_ID = 'dt.catalogproperties';
    public $MODULE_VERSION = '1.0.0';
    public $MODULE_VERSION_DATE = '2026-10-24';
    public $MODULE_NAME = 'Характеристики категорий';
    public $MODULE_DESCRIPTION = 'Наборы характеристик категорий каталога (инфоблок 16)';
    public $PARTNER_NAME = 'Discount Tools';
    public $PARTNER_URI = '';

    public function DoInstall()
    {
        global $APPLICATION;
        $root = rtrim((string)$_SERVER['DOCUMENT_ROOT'], '/');
        $wrapperSource = __DIR__.'/admin/dt_catalog_properties.php';
        $wrapperTarget = $root.'/bitrix/admin/dt_catalog_properties.php';

        if (!is_file($wrapperSource) || !is_dir(dirname($wrapperTarget))) {
            $APPLICATION->ThrowException('Не найден файл административной страницы или каталог /bitrix/admin.');
            return false;
        }
        if (is_file($wrapperTarget) && sha1_file($wrapperTarget) !== sha1_file($wrapperSource)) {
            $APPLICATION->ThrowException('Файл /bitrix/admin/dt_catalog_properties.php уже существует и отличается. Проверьте его вручную.');
            return false;
        }
        if (!is_file($wrapperTarget) && !copy($wrapperSource, $wrapperTarget)) {
            $APPLICATION->ThrowException('Не удалось создать /bitrix/admin/dt_catalog_properties.php.');
            return false;
        }
        RegisterModule($this->MODULE_ID);
        RegisterModuleDependences('main', 'OnAdminTabControlBegin', $this->MODULE_ID,
            'DT\\CatalogProperties\\AdminElementProperties', 'onAdminTabControlBegin', 100);
        RegisterModuleDependences('main', 'OnBuildGlobalMenu', $this->MODULE_ID,
            'DT\\CatalogProperties\\AdminMenu', 'onBuildGlobalMenu', 10000);
        return true;
    }

    public function DoUninstall()
    {
        UnRegisterModuleDependences('main', 'OnAdminTabControlBegin', $this->MODULE_ID,
            'DT\\CatalogProperties\\AdminElementProperties', 'onAdminTabControlBegin');
        UnRegisterModuleDependences('main', 'OnBuildGlobalMenu', $this->MODULE_ID,
            'DT\\CatalogProperties\\AdminMenu', 'onBuildGlobalMenu');
        UnRegisterModule($this->MODULE_ID);
        // Keep database tables, template integrations and admin wrapper for safe rollback.
        return true;
    }
}
