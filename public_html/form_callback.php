<?php
  if (isset($_POST['submit']) && $_POST['antispam'] == md5($_SERVER['SERVER_NAME'])) {
    if (isset($_POST['name']) && preg_match("/[^а-яё ]/iu", $_POST['name'])) {
      header('Location: 404');
      return;
    }

    $to = "info@rumgips.ru"; // кому
    $from = "rumgips@yandex.ru";

    if (isset($_POST['name'])) {
      $subject = "Заявка на обратный звонок в rumgips!"; // фиксированная тема письма
      // текст сообщения
      $message = "
      Имя: ".$_POST['name']."
      Телефон: ".$_POST['phone']."
      Комментарий: ".$_POST['comment']."
      rumgips.ru/";
    }

    if (isset($_POST['email'])) {
      $subject = "Новая подписка на новинки и акции в rumgips!";
      $message = "
      Email: ".$_POST['email']."
      Текст данной акции: Узнай первым о новинках, мероприятиях и спецпредложениях
      Скидка до 30% на первый заказ, новым пользователям";
    }

    $headers = "From: $from \r\n"; // конец заголовка письма

    /* Отправка сообщения, с помощью функции mail() */
    if (mail($to, $subject, $message, $headers . 'Content-type: text/plain; charset=utf-8')) {
			if (isset($_POST['name'])) 
				header('Location: thank-you');
			else
				header('Location: thank-you?subscription=1');
    } else {
      header('Location: 404');
    }
  }
?>
