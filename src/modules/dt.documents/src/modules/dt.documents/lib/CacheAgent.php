<?php
namespace DT\Documents;

use Bitrix\Main\Config\Option;

final class CacheAgent
{
    const EXPRESSION = '\\DT\\Documents\\CacheAgent::run();';

    public static function run()
    {
        if (Option::get('dt.documents', 'cache_agent', 'N') === 'Y') {
            try {
                CacheWorker::runOnce();
            } catch (\Throwable $e) {
                AddMessage2Log('dt.documents cache: '.$e->getMessage(), 'dt.documents');
            }
        }
        return self::EXPRESSION;
    }
}
