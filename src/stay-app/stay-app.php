<?php
    define('HOST','discount-tools.ru');
    define('EMAIL_HOST','discount-tools.ru');
    define('EMAIL_REPLY','no-reply@discount-tools.ru');
    define('EMAIL_TO', 'info@discount-tools.ru');
    
    $json = json_decode(file_get_contents('php://input'));
    $status = false;

    if(!empty($json)){
        $phone = !empty($json->phone) ? htmlspecialchars($json->phone) : null;
        $email = !empty($json->email) ? htmlspecialchars($json->email) : null;
        $url = !empty($json->url) ? htmlspecialchars($json->url) : null;
        
        $phone_num = preg_replace('/[^0-9]/', '', $phone);
        $datetime = date('Y-m-d H:i:s'); //дата отправки сообщения

        if( (!empty($phone)) && (!empty($email)) ) {
            $status = true;
        } else {
            $status = false;
            // ошибка
            // отправляем данные клиенту
            echo json_encode($status);
            die();
        }
        if(!empty($status)){
        ### Готовим заголовки
        //Отправляем сообщение на email
            $emailData['host'] = EMAIL_HOST;
            $emailData['email'] = EMAIL_REPLY;
            $emailData['subj'] = 'Сообщение "Продадим дешевле!" с сайта: ';
            $emailData['teaser'] = 'Посетитель сайта заинтересовался всплывающим сообщением и оставил свои контакты. Пожалуйста, свяжитесь с ним в рабочее время по указанным ниже контактным данным:';
            $emailData['text'] = '<p>Контактный телефон: <span><a href="tel:+'. $phone_num .'">'. $phone .'</a></span></p>
                                    <p>Контактный Email: <span><a href="mailto:'. $email .'">'. $email .'</a></span></p>
                                    <p>Страница на которой находился клиент: <span><a href="'. $url .'">посетить</a></span></p>
                                    <p>Время генерации сообщения: <span>'. $datetime .'</span></p>';
            $emailData['emailTo'] = EMAIL_TO;
    
        // Шаблон для письма
            include_once('tmpl_email.php');
            
            echo json_encode($status);
        }
    }