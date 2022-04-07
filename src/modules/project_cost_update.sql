
-- создание новой базы данных cv43140_test
-- mysql> CREATE DATABASE cv43140_test DEFAULT CHARACTER SET cp1251 DEFAULT COLLATE cp1251_general_ci;
CREATE DATABASE cv43140_test DEFAULT CHARACTER SET cp1251 DEFAULT COLLATE cp1251_general_ci;
-- создание нового пользователя и предоставление доступа к базе cv43140_test    
-- mysql> GRANT ALL PRIVILEGES ON cv43140_test.* TO cv43140_main@localhost IDENTIFIED BY 'password' WITH GRANT OPTION;
GRANT ALL PRIVILEGES ON cv43140_test.* TO cv43140_main@localhost IDENTIFIED BY 'password' WITH GRANT OPTION;
-- передоставим все права над базой данных cv43140_test уже существующему пользователю cv43140_main
-- mysql> GRANT ALL PRIVILEGES ON cv43140_test . * TO 'cv43140_main'@'localhost';
GRANT ALL PRIVILEGES ON cv43140_test . * TO 'cv43140_main'@'localhost';
-- Восстановление бэкапа базы в пустую таблицу
-- [root]# zcat cv43140_main.sql.gz | mysql -uroot -p cv43140_test
-- Подключиться к базе можно командой
use cv43140_test;
-- Список всех баз данных MySQL
-- mysql> SHOW DATABASES;
SHOW DATABASES;
-- удаление базы данных cv43140_test
-- mysql> DROP DATABASE cv43140_test;
DROP DATABASE cv43140_test;


-- Найти в таблице `b_iblock_element_property` ID товара по артикулу "11053"
--
-- RESULT 5749, 7137
SELECT IBLOCK_ELEMENT_ID AS PROD_ID FROM b_iblock_element_property WHERE VALUE = '11053' AND IBLOCK_PROPERTY_ID = 51

-- ПОИСК ДАННЫХ ДЛЯ БРЕНДА
-- ТАБЛИЦА `b_iblock_element`
-- Вывод списка всех брендов из таблицы где: 
-- IBLOCK_ID = 14 (ID инфоблока производителей)
-- ACTIVE = 'Y' (активные)
SELECT `ID`, `NAME`, `CODE` FROM b_iblock_element WHERE IBLOCK_ID = 14 AND ACTIVE = 'Y'

-- Найти в таблице "Rothenberger" где:
-- IBLOCK_ID = 14 (ID инфоблока производителей)
-- ACTIVE = 'Y' (активные)
-- NAME = 'Rothenberger' (имя бренда)
SELECT `ID`, `NAME`, `CODE` FROM b_iblock_element WHERE IBLOCK_ID = 14 AND ACTIVE = 'Y' AND NAME = 'Rothenberger'
-- RESULT (ID = 255, NAME = Rothenberger, CODE = rothenberger)

-- Наличие товара на складе таблица `b_catalog_product`
-- поля для обновления: 
-- QUANTITY = количество на складе, 
-- TIMESTAMP_X = время обновления, 
-- AVAILABLE = Y/N (наличие/отсутствие на складе) 
-- WHERE ID = id товара

-- Стоимость товара таблица `b_catalog_price`
-- поля для обновления:
-- PRICE = цена товара,
-- TIMESTAMP_X = время обновления,
-- PRICE_SCALE = цена товара,
-- WHERE PRODUCT_ID = id товара

-- таблица `b_iblock_element`
-- TIMESTAMP_X = время обновления,
-- MODIFIED_BY = (admin = 1) id пользователя, который делал последнее обновление
-- WHERE ID = id товара

-- Сопоставление бренда и товара
-- `b_iblock_16_index`
-- WHERE 
-- ELEMENT_ID = id товара 
-- AND
-- VALUE = id бренда


-- Запрос для получения ID товара по артикулу и имени бренда
SELECT prop.VALUE AS art, link.ELEMENT_ID AS prod_id, link.VALUE AS brand_id
FROM b_iblock_element_property AS prop
JOIN b_iblock_16_index AS link ON link.ELEMENT_ID = prop.IBLOCK_ELEMENT_ID
WHERE prop.VALUE = '11053' 
AND prop.IBLOCK_PROPERTY_ID = 51 
AND link.FACET_ID = 104 
AND link.VALUE = (SELECT ID FROM b_iblock_element WHERE IBLOCK_ID = 14 AND `NAME` = 'Rothenberger')
GROUP BY prop.ID


-- Получаем поля, которые надо обновить при обновлении стоимости товара
SELECT 
product.ID, 
price.PRICE AS price, 
price.PRICE_SCALE AS price_scale, 
product.QUANTITY AS qty, 
product.AVAILABLE AS stock_availability, 
element.MODIFIED_BY AS who_edit_id, 
element.TIMESTAMP_X
FROM b_catalog_product AS product
JOIN b_catalog_price AS price ON price.PRODUCT_ID = product.ID
JOIN b_iblock_element AS element ON element.ID = product.ID
WHERE product.ID = (
    SELECT link.ELEMENT_ID 
    FROM b_iblock_element_property AS prop 
    JOIN b_iblock_16_index AS link ON link.ELEMENT_ID = prop.IBLOCK_ELEMENT_ID 
    WHERE prop.VALUE = 'SEH006800' 
    AND prop.IBLOCK_PROPERTY_ID = 51 
    AND link.FACET_ID = 104 
    AND link.VALUE = 266
    GROUP BY prop.ID
)
GROUP BY product.ID;

-- Обновление полей
UPDATE b_catalog_price AS price, b_catalog_product AS product, b_iblock_element AS element
SET price.PRICE = :ammount, price.PRICE_SCALE = :ammount, product.QUANTITY = :qty, product.AVAILABLE = :available, 
element.MODIFIED_BY = :modyfied_by, element.TIMESTAMP_X = :timestamp_x
WHERE product.ID = (
    SELECT link.ELEMENT_ID 
    FROM b_iblock_element_property AS prop 
    JOIN b_iblock_16_index AS link ON link.ELEMENT_ID = prop.IBLOCK_ELEMENT_ID 
    WHERE prop.VALUE = :art 
    AND prop.IBLOCK_PROPERTY_ID = :iblock_id 
    AND link.FACET_ID = :facet_id 
    AND link.VALUE = :brand_id
    GROUP BY prop.ID
)
AND price.PRODUCT_ID = product.ID
AND element.ID = product.ID;