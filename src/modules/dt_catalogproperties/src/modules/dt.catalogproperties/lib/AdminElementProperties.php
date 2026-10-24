<?php
namespace DT\CatalogProperties;

use Bitrix\Main\Application;

final class AdminElementProperties
{
    const IBLOCK_ID = 16;

    public static function onAdminTabControlBegin(&$form)
    {
        if (!is_object($form) || !method_exists($form, 'GetName')) {
            return;
        }

        if ($form->GetName() !== 'form_element_16') {
            return;
        }

        $elementId = isset($_GET['ID']) ? (int)$_GET['ID'] : 0;

        // Для нового товара категория передаётся в URL.
        // Для существующего — определяется из базы данных.
        $isNewElement = ($elementId <= 0);

        $sectionId = 0;

        if ($isNewElement) {
            $sectionId = isset($_GET['IBLOCK_SECTION_ID'])
                ? (int)$_GET['IBLOCK_SECTION_ID']
                : 0;

            if ($sectionId <= 0) {
                return;
            }
        }

        // Создание копии товара пока не поддерживаем.
        if (
            !empty($_GET['action'])
            && $_GET['action'] === 'copy'
        ) {
            return;
        }

        try {
            $db = Application::getConnection();

            if (!$isNewElement) {
                $element = $db->query(
                    'SELECT IBLOCK_SECTION_ID
                    FROM b_iblock_element
                    WHERE ID = '.$elementId.'
                    AND IBLOCK_ID = '.self::IBLOCK_ID.'
                    LIMIT 1'
                )->fetch();

                if (!$element) {
                    return;
                }

                $sectionId = (int)$element['IBLOCK_SECTION_ID'];
            }

            if ($sectionId <= 0) {
                return;
            }

            // Если категория не настроена, форму не изменяем.
            $config = $db->query(
                'SELECT SECTION_ID
                 FROM dt_cp_section_config
                 WHERE SECTION_ID = '.$sectionId.'
                 LIMIT 1'
            )->fetch();

            if (!$config) {
                return;
            }

            $allowed = array();

            // Четыре служебных свойства.
            $serviceCodes = array(
                'ARTNUMBER',
                'MANUFACTURER',
                'COUNTRY',
                'GUARANTEE'
            );

            $quotedCodes = array();

            foreach ($serviceCodes as $code) {
                $quotedCodes[] =
                    "'".$db->getSqlHelper()->forSql($code)."'";
            }

            $serviceResult = $db->query(
                'SELECT ID
                 FROM b_iblock_property
                 WHERE IBLOCK_ID = '.self::IBLOCK_ID.'
                   AND CODE IN ('.implode(',', $quotedCodes).')'
            );

            while ($row = $serviceResult->fetch()) {
                $allowed[(int)$row['ID']] = true;
            }

            // Характеристики текущей категории.
            $propertyResult = $db->query(
                'SELECT PROPERTY_ID
                 FROM dt_cp_section_property
                 WHERE SECTION_ID = '.$sectionId.'
                 ORDER BY SORT_ORDER, PROPERTY_ID'
            );

            $propertyOrder = array();

            while ($row = $propertyResult->fetch()) {
                $propertyId = (int)$row['PROPERTY_ID'];

                $allowed[$propertyId] = true;
                $propertyOrder[] = $propertyId;
            }

            // Список технических свойств инфоблока.
            $technical = array();

            $allProperties = $db->query(
                'SELECT ID, CODE
                 FROM b_iblock_property
                 WHERE IBLOCK_ID = '.self::IBLOCK_ID
            );

            while ($row = $allProperties->fetch()) {
                $propertyId = (int)$row['ID'];
                $code = (string)$row['CODE'];

                if (preg_match('/^PROP[0-9]+$/i', $code)) {
                    $technical[$propertyId] = true;
                }
            }

            $hiddenCount = 0;
            $visibleCount = 0;

            // Обрабатываем HTML зарегистрированных полей CAdminForm.
            foreach ($form->arFields as $fieldId => &$field) {
                if (!preg_match('/^PROPERTY_([0-9]+)$/', (string)$fieldId, $matches)) {
                    continue;
                }

                $propertyId = (int)$matches[1];

                if (!isset($technical[$propertyId])) {
                    continue;
                }

                if (isset($allowed[$propertyId])) {
                    $visibleCount++;
                    continue;
                }

                if (
                    !isset($field['custom_html'])
                    || !is_string($field['custom_html'])
                ) {
                    continue;
                }

                $changed = 0;

                $newHtml = preg_replace(
                    '/^(\s*<tr)(?=[\s>])/i',
                    '$1 style="display:none"',
                    $field['custom_html'],
                    1,
                    $changed
                );

                if ($changed === 1 && $newHtml !== null) {
                    $field['custom_html'] = $newHtml;
                    $hiddenCount++;
                }
            }

            unset($field);

            // Сортировка технических характеристик внутри группы CAdminForm.
            $groupId = 'IBLOCK_ELEMENT_PROPERTY';

            if (
                isset($form->arFields[$groupId]['group'])
                && is_array($form->arFields[$groupId]['group'])
            ) {
                $originalGroup = $form->arFields[$groupId]['group'];
                $orderedGroup = array();

                // Сначала сохраняем системные и служебные свойства
                // в их первоначальном порядке.
                foreach ($originalGroup as $fieldId) {
                    if (!preg_match('/^PROPERTY_([0-9]+)$/', (string)$fieldId, $matches)) {
                        $orderedGroup[] = $fieldId;
                        continue;
                    }

                    $propertyId = (int)$matches[1];

                    if (!isset($technical[$propertyId])) {
                        $orderedGroup[] = $fieldId;
                    }
                }

                // Затем добавляем разрешённые технические свойства
                // строго в порядке SORT_ORDER.
                foreach ($propertyOrder as $propertyId) {
                    $fieldId = 'PROPERTY_'.$propertyId;

                    if (in_array($fieldId, $originalGroup, true)) {
                        $orderedGroup[] = $fieldId;
                    }
                }

                // Остальные технические свойства не удаляем:
                // они уже скрыты на уровне HTML.
                foreach ($originalGroup as $fieldId) {
                    if (!in_array($fieldId, $orderedGroup, true)) {
                        $orderedGroup[] = $fieldId;
                    }
                }

                $form->arFields[$groupId]['group'] = $orderedGroup;
            }

            unset($tab);

        } catch (\Throwable $e) {
            error_log(
                'DT AdminElementProperties error: '.$e->getMessage()
            );
        }
    }
}
