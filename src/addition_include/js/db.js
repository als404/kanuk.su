'use strict'
export const dataDocs = () => {
  const data = []
  let numArr = 0
  /*****************************************************************************/
  /************************### Паспорта/Инструкции ###***************************/
  /*****************************************************************************/
  // инструкции(паспорт изделия)
  data.push({
    linkTitle: 'инструкция, паспорт изделия',
    linkAlt: 'инструкция, паспорт изделия',
    liknText: 'Инструкция (паспорт)',
    template: 'manual',
  })
  /*****************************************************************************/
  /**********************### Декларации и сертификаты ###************************/
  /*****************************************************************************/
  // Декларация о соответствии на резьбонарезные станки для Esson
  data.push({
    brands: ['Esson'],
    arts: ['22000', '22010', '22020', '22021', '22022', '21002'],
    linkName: 'declarations-ElectricCUT-ESSON',
    linkTitle: 'Декларация о соответствии',
    linkAlt: 'Декларация о соответствии',
    liknText: 'Декларация о соответствии',
    template: 'document',
  })
  // Декларация о соответствии на желобонакатные станки для Esson
  data.push({
    brands: ['Esson'],
    arts: ['60002', '60003'],
    linkName: 'declarations-ElectricGRO-ESSON',
    linkTitle: 'Декларация о соответствии',
    linkAlt: 'Декларация о соответствии',
    liknText: 'Декларация о соответствии',
    template: 'document',
  })
  // отказное письмо №263 для Virax
  data.push({
    brands: ['Virax'],
    arts: [
      '240233',
      '240241',
      '240242',
      '240243',
      '240249',
      '240252',
      '250100',
      '250101',
      '250102',
      '250103',
      '250104',
      '250151',
      '250271',
      '250272',
      '250273',
      '250275',
      '250280',
      '250282',
      '250283',
      '250285',
      '250286',
      '251106',
      '251108',
      '251110',
      '251112',
      '251114',
      '251115',
      '251116',
      '251118',
      '010011',
      '010012',
      '010013',
      '010434',
      '010449',
      '010460',
      '010041',
      '010042',
      '010043',
      '010521',
      '010534',
      '010549',
      '010560',
      '010580',
      '013806',
      '013808',
      '013810',
      '013812',
      '013814',
      '013818',
      '013824',
      '013836',
      '013848',
      '013735',
      '013745',
      '013760',
      '013790',
      '013614',
      '013618',
      '013624',
      '010917',
      '010924',
      '010930',
      '011240',
      '018202',
      '018208',
      '018212',
      '018218',
      '018222',
      '018228',
      '014100',
      '014200',
      '014210',
      '011600',
      '011610',
      '011620',
      '017006',
      '017008',
      '017010',
      '017012',
      '017015',
      '017018',
      '017045',
      '017047',
      '017050',
      '017052',
      '220420',
      '220425',
      '200200',
      '200210',
      '200220',
      '200230',
      '200340',
      '200350',
      '253403',
      '210110',
      '210120',
      '210130',
      '210145',
      '210165',
      '210437',
      '210439',
      '210441',
      '210443',
      '210445',
      '210447',
      '210455',
      '210471',
      '210475',
      '210479',
      '210483',
      '210487',
      '210489',
      '210493',
      '210630',
      '211435',
      '215032',
      '211512',
      '211522',
      '211532',
      '211440',
      '211463',
      '215042',
      '136290',
      '136490',
      '136311',
      '138280',
      '136233',
      '236234',
      '136235',
      '136236',
      '136237',
      '136438',
      '136439',
      '262005',
      '262015',
      '262035',
      '262080',
      '250580',
      '252637',
      '252640',
      '252641',
      '252642',
      '252643',
      '252664',
      '252679',
      '252681',
      '253303',
    ],
    linkName: 'otkazpismo263',
    linkTitle: 'справка о принадлежности к объектам обязательного подтверждения соответствия',
    linkAlt: 'сертификат отказное письмо virax',
    liknText: 'Сертификат (отказное письмо)',
    template: 'document',
  })
  // отказное письмо 153/П для Brexit (153/П)
  data.push({
    brands: ['Brexit'],
    arts: ['2020020', '2020021', '2020022', '2020023', '2020024', '2020025', '4000115', '4000116', '4000117'],
    linkName: 'spravka_ob_podtverzhdenii_sootvetstviya',
    linkTitle: 'справка о принадлежности к объектам обязательного подтверждения соответствия',
    linkAlt: 'сертификат отказное письмо brexit',
    liknText: 'Сертификат (отказное письмо)',
    template: 'document',
  })
  // Декларация о соответствии на масло для Virax
  data.push({
    brands: ['Virax'],
    arts: ['110101', '110105', '110112', '110200', '110202', '110505', '110605', '290400'],
    linkName: 'declar_oil_virax',
    linkTitle: 'Декларация о соответствии',
    linkAlt: 'Декларация о соответствии',
    liknText: 'Декларация о соответствии',
    template: 'document',
  })
  // Декларация о соответствии на химические насосы BrexDECAl и BrexFLOW для Brexit
  data.push({
    brands: ['Brexit'],
    arts: [
      '6002001',
      '6002005',
      '6002011',
      '6002013',
      '6002021',
      '6002031',
      '6002050',
      '6002055',
      '6002065',
      '6001000',
    ],
    linkName: 'declarations-BrexDECAL-BrexFLOW-BREXIT',
    linkTitle: 'Декларация о соответствии',
    linkAlt: 'Декларация о соответствии',
    liknText: 'Декларация о соответствии',
    template: 'document',
  })
  // Декларация о соответствии на BrexJET Electirc и BrexTEST PRO для Brexit (BY/112 11.01. ТР010 003.02 00864)
  data.push({
    brands: ['Brexit'],
    arts: ['5000002', '5000004',],
    linkName: 'decl2021-brexjet_electric-brextest_pro',
    linkTitle: 'Декларация о соответствии',
    linkAlt: 'Декларация о соответствии',
    liknText: 'Декларация о соответствии',
    template: 'document',
  })
  // Декларация о соответствии на Оборудование для сварки плаcтиковых труб для ESSON (RU Д-CN.РА01.В.40518/21)
  data.push({
    brands: ['Esson'],
    arts: ['40040', '40063', '40064', '40065', '40110', '41160', '41250', '42160', '42250', '42315', '42630'],
    linkName: 'decl2021-cn-pa01-b-82739_21',
    linkTitle: 'Декларация о соответствии',
    linkAlt: 'Декларация о соответствии',
    liknText: 'Декларация о соответствии',
    template: 'document',
  })
  // Декларация о соответствии на BrexHEAT для Brexit (RU Д-BY.РА01.В.26663/21)
  data.push({
    brands: ['Brexit'],
    arts: ['6001200', '6001201', '6001202'],
    linkName: 'decl2021-eaecnrudby.pa01.b.26663-BREXIT',
    linkTitle: 'Декларация о соответствии',
    linkAlt: 'Декларация о соответствии',
    liknText: 'Декларация о соответствии',
    template: 'document',
  })
  // Декларация о соответствии на BrexFLOW PRO для Brexit (ЕАЭС N RU Д-BY.РА01.В.76325/21)
  data.push({
    brands: ['Brexit'],
    arts: ['6002051', '6002052', '6002053', '6002054'],
    linkName: 'decl2021_eacnrudby.pa01.b.76325-BREXIT',
    linkTitle: 'Декларация о соответствии',
    linkAlt: 'Декларация о соответствии',
    liknText: 'Декларация о соответствии',
    template: 'document',
  })
  // Свидетельство о государтсвенное регистрации продукции BrexTEX для Brexit (документ № 29370)
  data.push({
    brands: ['Brexit'],
    arts: ['6002151', '6002159', '6002160'],
    linkName: 'decl2021_BrexTEX_6002151_60021159_6002160-BREXIT',
    linkTitle: 'Свидетельство о государтсвенное регистрации продукции',
    linkAlt: 'Свидетельство о государтсвенное регистрации продукции',
    liknText: 'Свидетельство о государтсвенное регистрации продукции',
    template: 'document',
  })
  // Декларация о соответствии на BrexTEX для Brexit (документ № RU Д-IT.HA78.B.11355/19)
  data.push({
    brands: ['Brexit'],
    arts: ['0001110'],
    linkName: 'decl2019__RUd-IT.HA78.B.11355-19-BREXIT',
    linkTitle: 'Декларация о соответствии',
    linkAlt: 'Декларация о соответствии',
    liknText: 'Декларация о соответствии',
    template: 'document',
  })
  // Декларация о соответствии на BrexGROOVER для Brexit (документ № RU Д-CN.НА78.В.08121/19)
  data.push({
    brands: ['Brexit'],
    arts: ['2111000', '2111001', '2111002', '2111003'],
    linkName: 'decl2019__BrexGroover_2111000_2111001_2111002_2111003-BREXIT',
    linkTitle: 'Декларация о соответствии',
    linkAlt: 'Декларация о соответствии',
    liknText: 'Декларация о соответствии',
    template: 'document',
  })
  // Декларация о соответствии на BrexMATIC для Brexit (документ № RU Д-CN.НХ37.В.05715/20)
  data.push({
    brands: ['Brexit'],
    arts: [
      '2100100',
      '2100101',
      '2100102',
      '2100103',
      '2100104',
      '2100105',
      '2100106',
      '2100107',
      '2100108',
      '2100109',
      '2100110',
      '2100111',
      '2100112',
      '2100113',
      '2100114',
      '2100115',
      '2100116',
      '2100117',
      '2100118',
      '2100119',
      '2100120',
    ],
    linkName: 'decl2020__RU-D-CN.НХ37.В.05715-20-BREXIT',
    linkTitle: 'Декларация о соответствии',
    linkAlt: 'Декларация о соответствии',
    liknText: 'Декларация о соответствии',
    template: 'document',
  })
  // отказное письмо 13121 от 01.09.2021 B-Bend для Brexit (документ № 13121)
  data.push({
    brands: ['Brexit'],
    arts: ['3110001', '3110002', '3110003', '3110004'],
    linkName: 'otkazpismo13121-BREXIT',
    linkTitle: 'справка о принадлежности к объектам обязательного подтверждения соответствия',
    linkAlt: 'Декларация о соответствии',
    liknText: 'Сертификат (отказное письмо)',
    template: 'document',
  })
  // отказное письмо 13993 от 14.12.2021 на ручной инструмент для Brexit и ESSON (документ № 13993)
  data.push({
    brands: ['Brexit', 'Esson', 'Piranha'],
    arts: [
      '2020020',
      '2020021',
      '2020022',
      '2020023',
      '2020024',
      '2020025',
      '2020016',
      '2020017',
      '2020018',
      '2020019',
      '10001',
      '10002',
      '10003',
      '4000120',
      '4000115',
      '4000121',
      '4000122',
      '4000123',
      '4000133',
      '4000137',
      '4000140',
      '4000145',
      '4800004',
      '4000112',
      '4000113',
      '2100301',
      '2100300',
      '2100028',
      '2100029',
      '2100030',
      '2100031',
      '2100032',
      '2100033',
      '2100034',
      '2100035',
      '2100036',
      '2100037',
      '2100038',
      '2100039',
      '2100060',
      '2100061',
      '2100062',
      '2100063',
      '2100064',
      '2100065',
      '2100066',
      '2100067',
      '2100068',
      '2100069',
      '2100070',
      '2100071',
      '2100072',
      '2100073',
      '2100074',
      '2100075',
      '2100200',
      '2100201',
      '2100202',
      '2100203',
      '2100204',
      '2100205',
      '2100206',
      '2100207',
      '2100208',
      '2100209',
      '2100210',
      '2100211',
      '2100212',
      '2100213',
      '2120010',
      '20001',
      '20002',
      '2090005',
      'P003',
      'P005',
      'P006',
      '2100008',
      '2100009',
      '2100181',
      '2100183',
      '2100184',
      '2100193',
      '2100194',
      '2100195',
      '2100196',
      '2100015',
      '2100016',
      '2100017',
      '2100018',
      '2100019',
      '2100020',
      '2100021',
      '2100022',
      '2100023',
      '2100024',
      '2100025',
      '2100026',
      '22100',
      '22101',
      '22102',
      '22103',
      '22200',
      '22201',
      '22202',
      '22203',
      '22304',
      '22305',
      '22306',
      '22307',
      '22308',
      '22309',
      '22314',
      '22315',
      '22316',
      '22317',
      '22318',
      '22319',
      '2100137',
      '2100138',
      '2100139',
      '2100140',
      '2100141',
      '2100142',
      '2100143',
      '2100144',
      '2100145',
      '2100146',
      '2100147',
      '2100148',
      '2100149',
      '2100150',
      '2100151',
      '2100152',
      '2100153',
      '2100154',
      '2100155',
      '2100156',
      '2100157',
      '2100158',
      '2100159',
      '2100160',
      '2100161',
      '2100162',
      '2100163',
      '2100164',
      '2100167',
      '2100168',
      '2100169',
      '2100170',
      '2100171',
      '2100172',
      '2100173',
      '2100174',
      '2100175',
      '2100176',
      '2110011',
      '2110012',
      '2110013',
      '2110014',
      '2110015',
      '2110110',
      '2110111',
    ],
    linkName: 'otkazpismo13993',
    linkTitle: 'справка о принадлежности к объектам обязательного подтверждения соответствия',
    linkAlt: 'сертификат отказное письмо brexit esson',
    liknText: 'Сертификат (отказное письмо)',
    template: 'document',
  })

  // Декларация о соответствии для Brexit BrexDRIL и Esson ElectricDRIL (ЕАЭС N RU Д-CN.РА04.В.63347)
  data.push({
    brands: ['Brexit', 'Esson'],
    arts: [
      '1000110',
      '1000111',
      '1000130',
      '1000131',
      '1000150',
      '1000151',
      '1000160',
      '1000161',
      '1000205',
      '1000255',
      '1000256',
      '1000355',
      '1000356',
      '1000405',
      '1000406',
      '1000505',
      '1000506',
      '70132',
      '70152',
      '70165',
      '70205',
      '70255',
      '70305',
      '70355',
      '70405',
    ],
    linkName: 'decl2022-eaecnrudcn.pa04.b63347',
    linkTitle: 'Декларация о соответствии',
    linkAlt: 'Декларация о соответствии',
    liknText: 'Декларация о соответствии',
    template: 'document',
  })

  // Декларация о соответствии для Brexit и Esson (ЕАЭС N RU Д-BY.РА07.В.91320/22)
  data.push({
    brands: ['Brexit', 'Esson'],
    arts: [
      '6001002',
      '6001001',
      '60010',
      '2020030',
      '2020029',
      '2020034',
      '2020035',
      '2020036',
      '2020039',
    ],
    linkName: 'decl2022_eacnrudby.pa07.b91320-22',
    linkTitle: 'Декларация о соответствии',
    linkAlt: 'Декларация о соответствии',
    liknText: 'Декларация о соответствии',
    template: 'document',
  })

  // Декларация о соответствии на BrexDECAL и BrexFLOW для Brexit (документ № BY ЕАЭС № ИН/112 11.01. ЕЗ010 000.00 17951)
  data.push({
    brands: ['Brexit'],
    arts: [
      '6002030', 
      '6002035', 
      '6002040', 
      '6002045', 
      '6002090', 
      '6002095', 
      '6002050', 
      '6002051', 
      '6002056', 
      '6002057', 
      '6002065', 
      '6002070'],
    linkName: 'decl_eac-by112-1101-tp0100000017951',
    linkTitle: 'Декларация о соответствии',
    linkAlt: 'Декларация о соответствии',
    liknText: 'Декларация о соответствии',
    template: 'document',
  })

  /*****************************************************************************/
  /*******************************### Статьи ###*********************************/
  /*****************************************************************************/
  // статья "Размеры трубной резьбы: основные обозначения и стандарты"
  data.push({
    brands: [
      'Китай',
      'Piranha',
      'Virax',
      'REMS',
      'REKON',
      'RIDGID',
      'SUPER-EGO',
      'BREXIT',
      'Rothenberger',
      'HONGLI',
      'MGF',
      'Voll',
      'KERN',
      'Esson',
    ],
    arts: [
      'P006',
      '12r002',
      'P003',
      '136263',
      '136280',
      '136460',
      '136361',
      '136311',
      '520017',
      '026102',
      '65320',
      '36510',
      '36505',
      '36475',
      '65285',
      '65255',
      '13088',
      '13078',
      '13093',
      '13073',
      '13083',
      '600230200',
      '600240600',
      '600220600',
      'P005',
      '662200500',
      '662200300',
      '662200100',
      '12r114',
      '12r001',
      '200013',
      '200012',
      '070892X',
      '070781X',
      '62210',
      '600240900',
      '2100002',
      '30001',
      'ZITKY0150',
      '137563',
      '137563.1',
      '138021',
      '138021.1',
      '137530',
      '540025 R220',
      '540024 R220',
      '540023 R220',
      '540022 R220',
      '540020 R220',
      '530023 R220',
      '530022 R220',
      '530020 R220',
      '530014 R220',
      '530013 R220',
      '530010 R220',
      '901481',
      '026115',
      '026114',
      '026003',
      '026002',
      '16241',
      '12651',
      '44953',
      '44933',
      '44883',
      '44878',
      '864220200',
      '866200000',
      '32020',
      '2.10050',
      '71256',
      '71450',
      '2100102',
      '2100106',
      '2100107',
      '35011',
      '36011',
      '2100101',
      '162120',
      '162140',
      '770004 R380',
      '770003 R380',
      '750004 R380',
      '750003 R380',
      '380448 R220',
      '380447 R220',
      '380446 R380',
      '380445 R220',
      '380444 R220',
      '380443 R380',
      '380442 R220',
      '380441 R220',
      '380431 R380',
      '380430 R220',
      '380429 R220',
      '380428 R380',
      '380427 R220',
      '380426 R220',
      '380314 R380',
      '380313 R220',
      '380312 R220',
      '380311 R380',
      '380310 R220',
      '380309 R220',
      '380308 R380',
      '380307 R220',
      '380306 R220',
      '380305 R380',
      '380304 R220',
      '380303 R220',
      '340228 R380',
      '340227 R220',
      '340226 R220',
      '340222 R380',
      '340221 R220',
      '340220 R220',
      '340208 R380',
      '340207 R220',
      '340200 R220',
      '340201 R220',
      '340202 R380',
      '340206 R220',
      '020040',
      '020100',
      '020080',
      '020051',
      '020051',
      '020041',
      '0312102',
      '26107',
      '33511',
      '33501',
      '33491',
      '33471',
      '96057',
      '96067',
      '12451',
      '12441',
      '10981',
      '66630',
      '96052',
      '96047',
      '55212',
      '12881',
      '13191',
      '13191',
      '50697',
      '52020',
      '42020',
      '38020',
      '44020',
      '56057',
      '886420000',
      '47020',
      '43020',
      'sq10022',
      '2100030',
      '2100031',
      '2100032',
      '2100033',
      '2100029',
      '2100028',
      '2.00066',
      '2.00065',
      '2.00064',
      '2.00063',
      '2.00062',
      '2.00061',
      '6005D0000',
      '6006D0000',
      '6007D0000',
      '6008D0000',
      '6009D0000',
      '200061',
      '200062',
      '200063',
      '200064',
      '200065',
      '200066',
      '6002D0000',
      '6003D0000',
      '6004D0000',
      '136438',
      '136439',
      '136234',
      '136235',
      '136236',
      '136237',
      '136233',
      '136241',
      '070823x',
      '070824x',
      '070825x',
      '070826x',
      '070842x',
      '070843x',
      '136245',
      '136247',
      '136449',
      '136448',
      '136246',
      '136244',
      '136243',
      '136242',
      '136255',
      '136257',
      '136459',
      '136458',
      '136256',
      '136254',
      '136253',
      '136252',
      '136251',
      '070908x',
      '070912x',
      '070913x',
      '070914x',
      '070915x',
      '6000M2500',
      '6000M2000',
      '6000M1600',
      '2100021',
      '2100022',
      '2100023',
      '2100024',
      '2100025',
      '2100026',
      '136428',
      '136224',
      '136225',
      '136226',
      '136227',
      '136408',
      '136204',
      '136205',
      '136206',
      '136207',
      '6009D4000',
      '6008D4000',
      '6007D4000',
      '6006D4000',
      '6005D4000',
      '6004D4000',
      '24550',
      '21555',
      '24555',
      '21554',
      '24554',
      '21550',
      '21550',
      '200051',
      '200052',
      '200053',
      '200054',
      '200055',
      '200056',
      '6002D4000',
      '6003D4000',
      '24553',
      '24551',
      '24550',
      '21552',
      '24502',
      '21502',
      '24503',
      '24504',
      '21503',
      '21504',
      '21553',
      '24500',
      '24505',
      '21505',
      '24501',
      '21551',
      '21500',
      '21501',
      '070916X',
      '070918X',
      '070919X',
      '070920X',
      '070921X',
      '070945X',
      '070946X',
      '2100157',
      '2100152',
      '2100176',
      '2100175',
      '2100174',
      '2100173',
      '2100172',
      '2100171',
      '2100170',
      '2100161',
      '2100156',
      '2100153',
      '2100150',
      '2100151',
      '2100154',
      '2100155',
      '162210',
      '162211',
      '162212',
      '162213',
      '162214',
      '162215',
      '162216',
      '162217',
      '162218',
      '162209',
      '162309',
      '162205',
      '162207',
      '162208',
      '162304',
      '162302',
      '162303',
      '162202',
      '162203',
      '162204',
      '162220',
      '162221',
      'SQ50B.HSS0102',
      'SQ50B.HSS1234',
      '21005',
      '21055',
      '24053G',
      '24051G',
      'SQ50B.SS1234',
      'SQ50B.SS0102',
      '56059',
      '56058',
      '29002',
      '29052',
      '29003',
      '29053',
      '29004',
      '29054',
      '29005',
      '29055',
      '29006',
      '29056',
      '29007',
      '29057',
      '29008',
      '29058',
      '24003',
      '24053',
      '24052',
      '21053',
      '24001',
      '21051',
      '24050',
      '24051',
      '24004',
      '24054',
      '21054',
      '24005',
      '24055',
      '21501',
      '21003',
      '21001',
      '21004',
      '2100203',
      '2100202',
      '2100201',
      '162153',
      '162151',
      '162152',
      '22000',
      '22010',
      '22020',
      '22021',
      '22022',
      '21002',
    ],
    linkName: '',
    linkIMG: 'article001',
    linkSRC: '/reviews/razmery-trubnoy-rezby-osnovnye-oboznacheniya-i-standarty/',
    linkTitle: 'Размеры трубной резьбы',
    linkAlt: 'Размеры трубной резьбы',
    liknText: 'Размеры трубной резьбы',
    template: 'article',
  })
  // статья "Что значит SDR в маркировке полиэтиленовых труб"
  data.push({
    brands: ['Virax', 'BREXIT', 'HURNER'],
    arts: [
      '575022',
      '575023',
      '575024',
      '4000115',
      '4000116',
      '4000117',
      '4000118',
      '4000120',
      '4000121',
      '4000122',
      '4000123',
      '4000133',
      '4000135',
      '4000137',
      '4000140',
      '4000145',
      '216-100-191',
      '216-100-230',
      '4000201',
      '4000202',
      '4000203',
      '4000204',
      '4000205',
      '4000206',
      '4000207',
      '4000208',
      '4000209',
      '4000210',
      '4000211',
    ],
    linkName: '',
    linkIMG: 'article002',
    linkSRC: '/reviews/chto-znachit-sdr-v-markirovke-polietilenovykh-trub-/',
    linkTitle: 'Что значит SDR в маркировке полиэтиленовых труб',
    linkAlt: 'Что значит SDR в маркировке полиэтиленовых труб',
    liknText: 'Что значит SDR в маркировке полиэтиленовых труб',
    template: 'article',
  })
  // статья "Пошаговая инструкция по техническому обслуживанию желобонакаточных станков BrexGROOVER"
  data.push({
    brands: ['BREXIT'],
    arts: ['2111000', '2111001', '2111002', '2111003'],
    linkName: '',
    linkIMG: 'article_20220805',
    linkSRC:
      '/reviews/poshagovaya-instruktsiya-po-tekhnicheskomu-obsluzhivaniyu-zhelobonakatochnykh-stankov-brexgroover/',
    linkTitle: 'Пошаговая инструкция по техническому обслуживанию желобонакаточных станков BrexGROOVER',
    linkAlt: 'Пошаговая инструкция по техническому обслуживанию желобонакаточных станков BrexGROOVER',
    liknText: 'Пошаговая инструкция по техническому обслуживанию желобонакаточных станков BrexGROOVER',
    template: 'article',
  })
  /*****************************************************************************/
  // return object
  return data
}
