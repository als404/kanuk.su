<?php
// Входные данные
// input data <array> - $emailData
// $emailData['host'] = имя сайта (пример: prof-inst.by)
// $emailData['email'] = email отправителя (пример: no-reply@prof-inst.by)
// $emailData['subj'] = тема письма (пример: Регистрация на сайте)
// $emailData['teaser'] = анонс письма (пример: Спасибо за регистрацию на сайте!)
// $emailData['text'] = текс письма (пример: Вам осталось только подтвердить ваш email для этого пройдите по ссылке)
// $emailData['emailTo'] = email получателя (пример: user@email.com)

// Готовим заголовки
		$headers   = array();
		$headers[] = 'MIME-Version: 1.0';
		$headers[] = 'Content-Type: text/html; charset="UTF-8"';
		$headers[] = 'Content-Transfer-Encoding: 7bit';
		$headers[] = "From: ". $emailData['host'] ."<". $emailData['email'] .">";
		$headers[] = 'X-Mailer: PHP v'. phpversion();

		//$host_name = $_SERVER['HTTP_HOST'];
		$host_name = HOST;

// Готовим шаблон письма
		$subject = $emailData['subj'].' '. $host_name;
		$message = '
      	<html>
      		<head>
      			<title>'. $subject .'</title>
        		<style>
        			div.info { margin:10px 0; }
        			div.info p{ margin: 5px 0; font-style: italic; }
        			div.info p span { font-style: normal; font-weight:bold; }
        				.link { color: #2a282b; }
        		</style>
      		</head>
      		<body>
      			<h2>Доброго времени суток!</h2>
      			<p>'. $emailData['teaser'] .'</p>
      			<div class="info">'. $emailData['text'] .'</div>
          </body>
        </html>
    ';

// Отправляем почту
    mail($emailData['emailTo'], '=?UTF-8?B?'. base64_encode($subject). '?=', $message, implode("\r\n", $headers));