'use strict';
import {dataDocs} from './db.js';

document.addEventListener('DOMContentLoaded', () =>{
    (() => {
        // Константы и переменные
        const pathToFileExists = "/.file_exists.php"; // путь к фалу php, который проверяет наличие файла
        const pathDocs = "/upload/doc/"; // путь к папке с документами
        const pathToOther = `${pathDocs}other/`; // путь к папке с разными документами
        const pathToManual = `${pathDocs}manual/`; // путь к папке с паспортами/инструкциями
        const db = dataDocs(); // база с данными для показа на странице
        let parrentBox, childBox, beforeBox;

        // проверка наличия блоков в которые будем добавлять наши блоки на странице
        const checkingBox = () => {
            beforeBox = document.querySelector('.tabs-wrap'); // блок после которого будем добавлять
            parrentBox = document.querySelector('#addition-info');// родительский блок
            childBox = document.querySelector('.addition-info__item'); // блок в который будем добавлять
        }

        // функция перебор полей характеристик товара и возврат значения поля
        // return <string>
        const propertyValue = (selector, str) => {
            const props = document.querySelectorAll(selector);
            for (let i = 0; i < props.length; i++) {
                if (props[i].children[0].className == 'name' && props[i].children[0].textContent == str) {
                    return props[i].children[2].textContent.toLowerCase();
                }
            }
        }

        // функция поиска по массиву
        // return <boolean>
        const findValInObj = (arr, str) => {
            for (let i = 0; i < arr.length; i++) {
                if (arr[i].toLowerCase() == str) return true;
            }
        }
        // проверяем наличие бренда и артикула
        // записываем данные в объект[access]
        const accessView = (obj) => {
            obj.access = (findValInObj(obj.brands, obj.brand()) && findValInObj(obj.arts, obj.art())) ? true : false;
        }

        // объект 
        function Docs (brands = false, arts = false, linkName = false, linkTitle = '', linkText = '', linkAlt = '', linkSRC = false, linkIMG = false, imgWidth = '270', imgHeight = '382') {
            this.brands = brands;
            this.arts = arts;
            this.access = false;
            this.selector = ".catalog-detail-properties .catalog-detail-property";
            this.brand = () => { return propertyValue(this.selector, "Производитель"); };
            this.art = () => { return propertyValue(this.selector, "Артикул"); };
            this.urlPDF = () => { 
                if (linkName) {
                    return `${pathToOther + linkName}.pdf`;
                } else if (linkSRC) {
                    return linkSRC;
                } else {
                    return `${pathToManual + this.brand()}/${this.art()}.pdf`;
                }
            };
            this.urlPIC = () => { 
                if (linkName) {
                    return `${pathToOther}pic/${linkName}.jpg`;
                } else if (linkSRC) {
                    return `${pathToOther}pic/${linkIMG}.jpg`;
                } else {
                    return `${pathToManual + this.brand()}/pic/${this.art()}.jpg`;
                }
            };
            this.childCode = `<div class="addition-info__item"><a href="${this.urlPDF()}" target="_blank"><div class="addition-info__item_inner"><img class="manual" src="${this.urlPIC()}" width="${imgWidth}" height="${imgHeight}" alt="${linkAlt}" title="${linkTitle}"></div>${linkText}</a></div>`;
            this.parrentCode = `<div id="addition-info"><hr><h3>Полезная информация:</h3><div class="addition-info__container">${this.childCode}</div></div>`;
        }

        // проверяем наличие файла на сервере AJAX
        // получаем от сервера JSON-объект
        const ajaxFileExists = async (url, data = false) => {
            if(data) {
                let res = await fetch(url, {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        "Accept":       "application/json" 
                    },
                    body: data,
                });
                return await res.json();
            } else {
                return false;
            }
        };

        // генерируем блок и выводим на страницу
        // Вывод блоков. Первым будем проверять наличие файла инструкций/паспорта на сайте если
        // файл присутствует выводим его на страницу, затем будем выводить декларации и статьи.
        const viewBlock = (obj) => {
            checkingBox();
            if (beforeBox && obj.access) {
                if (parrentBox) {
                    childBox.insertAdjacentHTML('afterEnd', obj.childCode);
                } else {
                    beforeBox.insertAdjacentHTML('afterEnd', obj.parrentCode);
                }
            }
        }

        // функция подготавливает блок для показа и выводит его на страницу
        function prepPreview(obj) {
            if (obj.template == 'document') { // блок для декларации или сертификата
                const docs = new Docs(obj.brands, obj.arts, obj.linkName, obj.linkTitle, obj.liknText, obj.linkAlt);
                accessView(docs);
                viewBlock(docs);
            } else if (obj.template == 'article') { // блок для статьи
                const article = new Docs(obj.brands, obj.arts, obj.linkName, obj.linkTitle, obj.liknText, obj.linkAlt, obj.linkSRC, obj.linkIMG);
                accessView(article);
                viewBlock(article);
            }
        }

/*** подготавливаем блоки для вывода на страницу ***/ 

        // блок для паспорт/инструкция
        const manual = new Docs(false, false, false, db[0].linkTitle, db[0].liknText, db[0].linkAlt);
        const json = JSON.stringify({fileExists: manual.urlPDF()});

        // выводим блоки на страницу 
        ajaxFileExists(pathToFileExists, json)
            .then(data => { 
                if (data.file) { 
                    manual.access = data.file; 
                } 
            })
            .then( () => {
                viewBlock(manual);
                for (let i = 1; i < db.length; i++) {
                    prepPreview(db[i]);
                }
            });
    })(); 
});
