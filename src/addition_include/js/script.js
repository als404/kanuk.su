// Константы и переменные
const pathToFileExists = "/.file_exists.php"; // путь к фалу php, который проверяет наличие файла
const pathDocs = "/docs/"; // путь к папке с документами
const pathToOther = `${pathDocs}other/`; // путь к папке с разными документами
const pathToManual = `${pathDocs}manual/`; // путь к папке с паспортами/инструкциями
let parrentBox, childBox, beforeBox, brands,  arts, linkName, linkAlt, linkTitle, liknText, linkSRC, linkIMG;

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
const ajaxFileExists = async (url, data) => {
    let res = await fetch(url, {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
			"Accept":       "application/json" 
        },
        body: data,
    });
    return await res.json();
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

// подготавливаем блоки для вывода на страницу

//*****************************************************************************/
// инструкции(паспорт изделия)
linkTitle = 'инструкция, паспорт изделия';
linkAlt = linkTitle;
liknText = 'Инструкция (паспорт)';

const manual = new Docs(false, false, false, linkTitle, liknText, linkAlt);
const json = JSON.stringify({fileExists: manual.urlPDF()});

//*****************************************************************************/
// Декларация о соответствии на резьбонарезные станки для Esson
brands = ["Esson"];
arts = ["22000", "22010", "22020", "22021", "22022", "21002"];
linkName = 'declarations-ElectricCUT-ESSON';
linkTitle = 'Декларация о соответствии';
linkAlt = linkTitle;
liknText = linkTitle;

const declarationsElectricCutEsson = new Docs(brands, arts, linkName, linkTitle, liknText, linkAlt);

//*****************************************************************************/
// Декларация о соответствии на желобонакатные станки для Esson
brands = ["Esson"];
arts = ["60002", "60003"];
linkName = 'declarations-ElectricGRO-ESSON';
linkTitle = 'Декларация о соответствии';
linkAlt = linkTitle;
liknText = linkTitle;

const declarationsElectricGroEsson = new Docs(brands, arts, linkName, linkTitle, liknText, linkAlt);

//*****************************************************************************/
// отказное письмо №263 для Virax
brands = ["Virax"];
arts = ["240233", "240241", "240242", "240243", "240249", "240252", "250100", "250101", "250102", "250103", "250104", "250151", "250271", "250272", "250273", "250275", "250280", "250282", "250283", "250285", "250286", "251106", "251108", "251110", "251112", "251114", "251115", "251116", "251118", "010011", "010012", "010013", "010434", "010449", "010460", "010041", "010042", "010043", "010521", "010534", "010549", "010560", "010580", "013806", "013808", "013810", "013812", "013814", "013818", "013824", "013836", "013848", "013735", "013745", "013760", "013790", "013614", "013618", "013624", "010917", "010924", "010930", "011240", "018202", "018208", "018212", "018218", "018222", "018228", "014100", "014200", "014210", "011600", "011610", "011620", "017006", "017008", "017010", "017012", "017015", "017018", "017045", "017047", "017050", "017052", "220420", "220425", "200200", "200210", "200220", "200230", "200340", "200350", "253403", "210110", "210120", "210130", "210145", "210165", "210437", "210439", "210441", "210443", "210445", "210447", "210455", "210471", "210475", "210479", "210483", "210487", "210489", "210493", "210630", "211435", "215032", "211512", "211522", "211532", "211440", "211463", "215042", "136290", "136490", "136311", "138280", "136233", "236234", "136235", "136236", "136237", "136438", "136439", "262005", "262015", "262035", "262080", "250580", "252637", "252640", "252641", "252642", "252643", "252664", "252679", "252681", "253303"];
linkName = 'otkazpismo263';
linkTitle = 'справка о принадлежности к объектам обязательного подтверждения соответствия';
linkAlt = 'сертификат_отказное_письмо_virax.png';
liknText = 'Сертификат (отказное письмо)';

const otkazpismo263 = new Docs(brands, arts, linkName, linkTitle, liknText, linkAlt);

//*****************************************************************************/
// отказное письмо №823 для Brexit
brands = ["Brexit"];
arts = ["2020020", "2020021", "2020022", "2020023", "2020024", "2020025", "4000115", "4000116", "4000117"];
linkName = 'spravka_ob_podtverzhdenii_sootvetstviya';
linkTitle = 'справка о принадлежности к объектам обязательного подтверждения соответствия';
linkAlt = 'сертификат_отказное_письмо_brexit.png';
liknText = 'Сертификат (отказное письмо)';

const spravkaObPodtverzhdeniiSootvetstviya = new Docs(brands, arts, linkName, linkTitle, liknText, linkAlt);

//*****************************************************************************/
// Декларация о соответствии на масло для Virax
brands = ["Virax"];
arts = ["110101", "110105", "110112", "110200", "110202", "110505", "110605", "290400"];
linkName = 'declar_oil_virax';
linkTitle = 'Декларация о соответствии';
linkAlt = linkTitle;
liknText = linkTitle;

const declarOilVirax = new Docs(brands, arts, linkName, linkTitle, liknText, linkAlt);

//*****************************************************************************/
// Декларация о соответствии на химические насосы BrexDECAl и BrexFLOW для Brexit
brands = ["Brexit"];
arts = ["6002001", "6002005", "6002011", "6002013", "6002021", "6002031", "6002050", "6002055", "6002065", "6001000", "6001001"];
linkName = 'declarations-BrexDECAL-BrexFLOW-BREXIT';
linkTitle = 'Декларация о соответствии';
linkAlt = linkTitle;
liknText = linkTitle;

const declarationsBrexDecalBrexFlowBrexit = new Docs(brands, arts, linkName, linkTitle, liknText, linkAlt);

//*****************************************************************************/
// статья "Размеры трубной резьбы: основные обозначения и стандарты"
brands = ["Китай", "Piranha", "Virax", "REMS", "REKON", "RIDGID", "SUPER-EGO", "BREXIT", "Rothenberger", "HONGLI", "MGF", "Voll", "KERN", "Esson"];
arts = ["P006", "12r002", "P003", "136263", "136280", "136460", "136361", "136311", "520017", "026102", "65320", "36510", "36505", "36475", "65285", "65255", "13088", "13078", "13093", "13073", "13083", "600230200", "600240600", "600220600", "P005", "662200500", "662200300", "662200100", "12r114", "12r001", "200013", "200012", "070892X", "070781X", "62210", "600240900", "2100002", "30001", "ZITKY0150", "137563", "137563.1", "138021", "138021.1", "137530", "540025 R220", "540024 R220", "540023 R220", "540022 R220", "540020 R220", "530023 R220", "530022 R220", "530020 R220", "530014 R220", "530013 R220", "530010 R220", "901481", "026115", "026114", "026003", "026002", "16241", "12651", "44953", "44933", "44883", "44878", "864220200", "866200000", "32020", "2.10050", "71256", "71450", "2100102", "2100106", "2100107", "35011", "36011", "2100101", "162120", "162140", "770004 R380", "770003 R380", "750004 R380", "750003 R380", "380448 R220", "380447 R220", "380446 R380", "380445 R220", "380444 R220", "380443 R380", "380442 R220", "380441 R220", "380431 R380", "380430 R220", "380429 R220", "380428 R380", "380427 R220", "380426 R220", "380314 R380", "380313 R220", "380312 R220", "380311 R380", "380310 R220", "380309 R220", "380308 R380", "380307 R220", "380306 R220", "380305 R380", "380304 R220", "380303 R220", "340228 R380", "340227 R220", "340226 R220", "340222 R380", "340221 R220", "340220 R220", "340208 R380", "340207 R220", "340200 R220", "340201 R220", "340202 R380", "340206 R220", "020040", "020100", "020080", "020051", "020051", "020041", "0312102", "26107", "33511", "33501", "33491", "33471", "96057", "96067", "12451", "12441", "10981", "66630", "96052", "96047", "55212", "12881", "13191", "13191", "50697", "52020", "42020", "38020", "44020", "56057", "886420000", "47020", "43020", "sq10022", "2100030", "2100031", "2100032", "2100033", "2100029", "2100028", "2.00066", "2.00065", "2.00064", "2.00063", "2.00062", "2.00061", "6005D0000", "6006D0000", "6007D0000", "6008D0000", "6009D0000", "200061", "200062", "200063", "200064", "200065", "200066", "6002D0000", "6003D0000", "6004D0000", "136438", "136439", "136234", "136235", "136236", "136237", "136233", "136241", "070823x", "070824x", "070825x", "070826x", "070842x", "070843x", "136245", "136247", "136449", "136448", "136246", "136244", "136243", "136242", "136255", "136257", "136459", "136458", "136256", "136254", "136253", "136252", "136251", "070908x", "070912x", "070913x", "070914x", "070915x", "6000M2500", "6000M2000", "6000M1600", "2100021", "2100022", "2100023", "2100024", "2100025", "2100026", "136428", "136224", "136225", "136226", "136227", "136408", "136204", "136205", "136206", "136207", "6009D4000", "6008D4000", "6007D4000", "6006D4000", "6005D4000", "6004D4000", "24550", "21555", "24555", "21554", "24554", "21550", "21550", "200051", "200052", "200053", "200054", "200055", "200056", "6002D4000", "6003D4000", "24553", "24551", "24550", "21552", "24502", "21502", "24503", "24504", "21503", "21504", "21553", "24500", "24505", "21505", "24501", "21551", "21500", "21501", "070916X", "070918X", "070919X", "070920X", "070921X", "070945X", "070946X", "2100157", "2100152", "2100176", "2100175", "2100174", "2100173", "2100172", "2100171", "2100170", "2100161", "2100156", "2100153", "2100150", "2100151", "2100154", "2100155", "162210", "162211", "162212", "162213", "162214", "162215", "162216", "162217", "162218", "162209", "162309", "162205", "162207", "162208", "162304", "162302", "162303", "162202", "162203", "162204", "162220", "162221", "SQ50B.HSS0102", "SQ50B.HSS1234", "21005", "21055", "24053G", "24051G", "SQ50B.SS1234", "SQ50B.SS0102", "56059", "56058", "29002", "29052", "29003", "29053", "29004", "29054", "29005", "29055", "29006", "29056", "29007", "29057", "29008", "29058", "24003", "24053", "24052", "21053", "24001", "21051", "24050", "24051", "24004", "24054", "21054", "24005", "24055", "21501", "21003", "21001", "21004", "2100203", "2100202", "2100201", "162153", "162151", "162152", "22000", "22010", "22020", "22021", "22022", "21002"];
linkName = '';
linkSRC = '/reviews/razmery-trubnoy-rezby-osnovnye-oboznacheniya-i-standarty/';
linkIMG = 'article001';
linkTitle = 'Размеры трубной резьбы';
linkAlt = linkTitle;
liknText = linkTitle;

const article001 = new Docs(brands, arts, linkName, linkTitle, liknText, linkAlt, linkSRC, linkIMG);

//*****************************************************************************/
// статья "Что значит SDR в маркировке полиэтиленовых труб"
brands = ["Virax", "BREXIT", "HURNER"];
arts = ["575022", "575023", "575024", "4000115", "4000117", "216-100-191", "216-100-230", "4000201", "4000202", "4000203", "4000204", "4000205", "4000206", "4000207", "4000208", "4000209", "4000210", "4000211"];
linkName = '';
linkIMG = 'article002';
linkSRC = '/reviews/chto-znachit-sdr-v-markirovke-polietilenovykh-trub-/';
linkTitle = 'Что значит SDR в маркировке полиэтиленовых труб';
linkAlt = linkTitle;
liknText = linkTitle;

const article002 = new Docs(brands, arts, linkName, linkTitle, liknText, linkAlt, linkSRC, linkIMG);

//*****************************************************************************/
ajaxFileExists(pathToFileExists, json)
.then(data => { if (data.file) { manual.access = data.file; } })
.then( () => {
// Паспорт/инструкция
	viewBlock(manual);
// Декларации и письма
	// Декларация о соответствии на резьбонарезные станки для Esson
	accessView(declarationsElectricCutEsson);
	viewBlock(declarationsElectricCutEsson);
	// Декларация о соответствии на желобонакатные станки для Esson
	accessView(declarationsElectricGroEsson);
	viewBlock(declarationsElectricGroEsson);
	// отказное письмо №263 для Virax
	accessView(otkazpismo263);
	viewBlock(otkazpismo263);
	// отказное письмо №823 для Brexit
	accessView(spravkaObPodtverzhdeniiSootvetstviya);
	viewBlock(spravkaObPodtverzhdeniiSootvetstviya);
	// Декларация о соответствии на масло для Virax
	accessView(declarOilVirax);
	viewBlock(declarOilVirax);
	// Декларация о соответствии на химические насосы BrexDECAl и BrexFLOW для Brexit
	accessView(declarationsBrexDecalBrexFlowBrexit);
	viewBlock(declarationsBrexDecalBrexFlowBrexit);
// Статьи
	// статья "Размеры трубной резьбы: основные обозначения и стандарты"
	accessView(article001);
	viewBlock(article001);
	// статья "Что значит SDR в маркировке полиэтиленовых труб"
	accessView(article002);
	viewBlock(article002);
});