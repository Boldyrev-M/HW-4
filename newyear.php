<?php
/**
 * User: Max
 * Date: 01.01.2017
 * Time: 19:38
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
echo PHP_EOL;
/*  получение данных от VK    */
$URL_Name = "https://api.vk.com/method/newsfeed.search?q=%22%D1%81%20%D0%BD%D0%BE%D0%B2%D1%8B%D0%BC%20%D0%B3%D0%BE%D0%B4%D0%BE%D0%BC%22";
// задание вручную строки для поиска, если VK тупит
if (isset($_GET['manually']) && strlen($_GET['manually'])) {
    $URL_Name = "https://api.vk.com/method/newsfeed.search?q=%22" . urlencode($_GET['manually']) . "%22";
    }

$fp_vk_url_content = fopen( $URL_Name ,"r");
$urlContent = stream_get_contents($fp_vk_url_content);
fclose($fp_vk_url_content);
$vk_json_array = json_decode($urlContent, true);

//- выкинуть первый элемент
array_shift($vk_json_array['response']);
//var_dump($vk_json_array);

if (sizeof($vk_json_array ["response"]) == 0 ) {
    die("Ответ от API пустой, перегрузите страницу или измените поиск: ?manually=нужный_текст!");
}
/*  отбор нужных данных   */
$photo_array = array(); // сюда будем сохранять подходящие посты
$laiki = array(); //    сюда сохраняем количество лайков в посте
$current_post = 0;
foreach ( $vk_json_array['response'] as  $post) {   // разбираем полученный ответ
    if (array_key_exists('attachment', $post)) {    // ищем attachment с photo
        if ($post['attachment']['type'] == 'photo') {
            $photo_array[] = $post;
            $laiki[] = $post['likes']['count'];
            // https://regex101.com/
            // обработка текста вида "Мы с [id1309724|Антоном Беляевым] поздравляем"
            // в итоге хотим оставить "Мы с Антоном Беляевым поздравляем"
            // превращаем код id в имя юзера - ищем вхождения вида:
            // 1- "[id" . многоцифр . "|"
            // 2- всёкромезакрывающейквадратнойскобки
            // 3- "]"
            // всё это заменяем на часть 2 "всёкромезакрывающейквадратнойскобки"
            $searchUserid = '/(\[id\d+\|)([^\]]+)(\])/u';
            $textNoBrackets = preg_replace($searchUserid, '$2' , $post['text'] );

            // знаки препинания - это всё, что не буквы и пробелы
            if (preg_match('/([a-zA-Zа-яА-ЯЁё0-9 ]*)([^a-zA-Zа-яА-ЯЁё0-9 ])/u', $textNoBrackets, $str_before_commas) == 0) {
                // не найден знак препинания-то: сами нарисуем массив!
                $str_before_commas[0] = $textNoBrackets;
                $str_before_commas[1] = $textNoBrackets;
                $str_before_commas[2] = " ";
            };
            /* $str_before_commas[0] - вся подстрока
               $str_before_commas[1] - подстрока до первого знака препинания
               $str_before_commas[2] - и он сам
            */
            if (strlen($str_before_commas[1])) { // текст вообще-то есть?
                $words = explode(" ", $str_before_commas[1]); // разбиваем на слова
                if (count($words) > 5) {
                    // разбить [1] на слова, если их > 5 - берем первые 5 и ставим многоточие
                    $photo_array[$current_post]['text'] = $words[0] . " " . $words[1] . " " .
                        $words[2] . " " . $words[3] . " " . $words[4] . '...';
                } else { // слов не больше 5
                    if ($str_before_commas[2] !== ".") {
                        //  если не стоит в конце точка - добавляем многоточие
                        $photo_array[$current_post]['text'] = $str_before_commas[1] . '...';
                    } else {
                        // а если точка, то и берём текст до неё - согласно заданию!
                        $photo_array[$current_post]['text'] = $str_before_commas[1];
                    }
                }
            }
            else {  // нет текста, будет только многоточие
                $photo_array[$current_post]['text'] = "...";
            }
            $current_post++;
        }
    }
}
// выводим данные в режиме отладки
if (isset($_GET['test']) && ($_GET['test'] == 1) )  {
    echo "<pre>".print_r($photo_array,true)."</pre>";
}

if (isset($_GET['test']) && ($_GET['test'] == 1) )  {
    echo "<br>Лайки ДО сортировки: <pre>".print_r($laiki,true)."</pre><br>";
}

arsort ($laiki);

if (isset($_GET['test']) && ($_GET['test'] == 1) )  {
    echo "<br>Лайки ПОСЛЕ сортировки: <pre>".print_r($laiki,true)."</pre><br>";
}

/*  формируем текст для html */
$html_table = ''; // будущая строка для внедрения таблички в html шаблон
$row_flag = 0;
foreach ($laiki as $i => $num_likes) {
    if ($row_flag == 0) $html_table .= '<div class="row">';// начало ряда
    $html_table .= '<div class="col-md-3 portfolio-item">
                    <p>' . $photo_array[$i]['text'] . '</p>
                    <p> Лайков: ' . $num_likes . ' </p>
                    <a target = "blank" href="https://vk.com/feed?w=wall' . $photo_array[$i]['owner_id'] . '_'.$photo_array[$i]['id'] . '">
                    <img class="img-responsive" src="' . $photo_array[$i]['attachment']['photo']['src'] . '" alt="">
                    
                </a>
            </div>';
    $row_flag++;
    if ($row_flag == 4) { // конец ряда - закрываем "row"
        $html_table.= '</div>';
        $row_flag = 0;
    }
}
if (!$row_flag) {   // последний ряд не был окончен - закрываем ряд
    $html_table.= '</div>';
}
$html_table.= '</div>'; // и саму таблицу

// заменяем тело шаблона нашей строкой
$html_index_file = file_get_contents("./startbootstrap/index.html","r");
$html_index_file = preg_replace('/\<!-- DATA_TABLE --\>/', $html_table, $html_index_file);
echo $html_index_file;
