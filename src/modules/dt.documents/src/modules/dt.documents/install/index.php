<?php
use Bitrix\Main\Application;

class dt_documents extends CModule
{
    public $MODULE_ID = 'dt.documents';
    public $MODULE_VERSION;
    public $MODULE_VERSION_DATE;
    public $MODULE_NAME = 'Документы товаров (DT)';
    public $MODULE_DESCRIPTION = 'Материалы в карточках товаров каталога 16';
    public $PARTNER_NAME = 'Discount Tools';
    public $PARTNER_URI = 'https://discount-tools.ru/';

    public function __construct()
    {
        include __DIR__.'/version.php';
        $this->MODULE_VERSION = $arModuleVersion['VERSION'];
        $this->MODULE_VERSION_DATE = $arModuleVersion['VERSION_DATE'];
    }

    public function DoInstall()
    {
        global $APPLICATION;
        try {
            if (!\Bitrix\Main\Loader::includeModule('iblock')) {
                throw new \RuntimeException('Нужен модуль iblock.');
            }
            $this->InstallDB();
            $this->InstallFiles();
            RegisterModule($this->MODULE_ID);
            RegisterModuleDependences('main', 'OnBuildGlobalMenu', $this->MODULE_ID,
                'DT\\Documents\\AdminMenu', 'onBuildGlobalMenu');
            \Bitrix\Main\Config\Option::set($this->MODULE_ID, 'public_enabled', 'N');
            \Bitrix\Main\Config\Option::set($this->MODULE_ID, 'auto_manual', 'Y');
            \Bitrix\Main\Config\Option::set($this->MODULE_ID, 'cache_host', '');
            \Bitrix\Main\Config\Option::set($this->MODULE_ID, 'cache_agent', 'N');
        } catch (\Throwable $e) {
            $APPLICATION->ThrowException('Ошибка установки dt.documents: '.$e->getMessage().
                '. Частично созданные таблицы сохраняются для ручной проверки.');
            return false;
        }
        return true;
    }

    public function InstallDB()
    {
        $db = Application::getConnection();
        $tables = array('dt_doc_document','dt_doc_brand','dt_doc_article','dt_doc_section','dt_doc_cache_task');
        $existing = array();
        foreach ($tables as $table) {
            if ($db->isTableExists($table)) {
                $existing[] = $table;
            }
        }
        if ($existing) {
            throw new \RuntimeException('Таблицы уже существуют: '.implode(', ', $existing).
                '. Автоматическое перезаписывание запрещено.');
        }
        $schema = file_get_contents(__DIR__.'/db/mysql/install.sql');
        if ($schema === false) {
            throw new \RuntimeException('Не найден SQL-файл схемы.');
        }
        foreach (explode(';', $schema) as $statement) {
            if (trim($statement) !== '') {
                $db->queryExecute(trim($statement));
            }
        }
        return true;
    }

    public function InstallFiles()
    {
        $src = __DIR__.'/admin/';
        $dst = $_SERVER['DOCUMENT_ROOT'].'/bitrix/admin/';
        foreach (array('dt_documents_list.php', 'dt_documents_edit.php',
            'dt_documents_import.php', 'dt_documents_settings.php', 'dt_documents_preview.php', 'dt_documents_cache.php') as $filename) {
            $origin = $src.$filename;
            $target = $dst.$filename;
            if (file_exists($target) && hash_file('sha256', $origin) !== hash_file('sha256', $target)) {
                throw new \RuntimeException('Обёртка '.$filename.' уже существует и отличается.');
            }
        }
        foreach (array('dt_documents_list.php', 'dt_documents_edit.php',
            'dt_documents_import.php', 'dt_documents_settings.php', 'dt_documents_preview.php', 'dt_documents_cache.php') as $filename) {
            if (!is_file($dst.$filename) && !copy($src.$filename, $dst.$filename)) {
                throw new \RuntimeException('Не удалось установить '.$filename.'.');
            }
        }
        return true;
    }

    public function DoUninstall()
    {
        // Deliberately preserve tables, documents and custom template integration.
        UnRegisterModuleDependences('main', 'OnBuildGlobalMenu', $this->MODULE_ID,
            'DT\\Documents\\AdminMenu', 'onBuildGlobalMenu');
        \CAgent::RemoveAgent('\\DT\\Documents\\CacheAgent::run();', $this->MODULE_ID);
        UnRegisterModule($this->MODULE_ID);
        \Bitrix\Main\Config\Option::delete($this->MODULE_ID);
        // Do not remove files automatically: wrappers may have been customized.
        return true;
    }
}
