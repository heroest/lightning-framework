<?php
namespace App\Controller;
use function Lightning\{awaitForResult, await, container};

class TestController
{
    public function showNameAction($name)
    {
        echo "hi, {$name}\r\n";
    }

    public function databaseTestAction($id)
    {
        $student_list = range(1000, 1010);
        $dbm = container()->get('dbm');
        foreach ($student_list as $id) {
            $sql = "SELECT id, nick FROM user WHERE id = {$id} ORDER BY RAND() DESC LIMIT 1";
            $promise = $dbm->query('vip-music', $sql, 'slave', true);
            $promise->then(function($query_result) {
                echo '1-' . json_encode($query_result->result, JSON_UNESCAPED_UNICODE) . "\r\n";
                return $query_result;
            }, function ($error){
                var_dump($error);
                return $error;
            });
            unset($promise);
        }

        foreach ($student_list as $id) {
            $sql = "SELECT id, nick FROM user WHERE id = {$id} ORDER BY RAND() DESC LIMIT 1";
            $promise = $dbm->query('vip-music', $sql, 'slave', true);
            $promise->then(function($query_result) {
                echo '2-' . json_encode($query_result->result, JSON_UNESCAPED_UNICODE) . "\r\n";
                return $query_result;
            }, function ($error){
                var_dump($error);
                return $error;
            });
            unset($promise);
        }

        $this->memoryWatch();
        // $loop->addPeriodicTimer(15, function(){
        //     echo "gc collected\r\n";
        //     gc_collect_cycles();
        // });
        echo "waiting\r\n";
        // return true;
    }

    public function testInsertAction()
    {
        $dbm = container()->get('dbm');
        foreach (range(1, 30000) as $name) {
            $sql = "INSERT INTO `odd_hd_message` (student_id , content, extra_data, template_key, action_user_id, delete_comment) 
                VALUES (709711, '测试插入数据12138', '', 'registration_success', 0, '')";
            $promise = $dbm->query('vip-ext', $sql, 'master');
            $promise->then(function($query_result) {
                // echo "inserted: {$query_result->lastInsertId}\r\n";
            }, function($error){
                echo $error->getMessage() . "\r\n";
            });
        }

        $this->memoryWatch();
        echo "waiting\r\n";
    }

    public function homeSelectAction()
    {
        $dbm = container()->get('dbm');
        foreach (range(1, 1000) as $i) {
            $sql = "SELECT {$i} FROM user ORDER BY RAND() LIMIT 1";
            $promise = $dbm->query('home-db', $sql, 'slave', true);
            $promise->then(function($query_result) {
                echo json_encode($query_result->result, JSON_UNESCAPED_UNICODE) . "\r\n";
            }, function($error){
                echo "error: " . $error->getMessage() . "\r\n";
            });
        }
        $this->memoryWatch();
        echo "started\r\n";
    }

    public function homeInsertAction()
    {
        $dbm = container()->get('dbm');
        $sql = "INSERT INTO user (nickname, showname, password, salt) VALUES ('heroest', 'neosteam', '123456', 'abcabc')";
        foreach (range(1, 25000) as $i) {
            $promise = $dbm->query('home-db', $sql, 'master');
        }
        $this->memoryWatch();
        echo "started\r\n";
    }

    public function mqWatcherAction()
    {
        $client = container()->get('http-client');
        $promise  = $client->get('https://www.jandan.net');
        $promise->then(function($result){
            echo $result->code . '-jandan:' . $result->time_end . "\r\n";
        }, function($error){
            echo $error->getMessage();
        });
        $promise = $client->get('http://www.baidu.com');
        $promise->then(function($result){
            echo $result->code . '-baidu:' . $result->time_end . "\r\n";
        }, function($error){
            echo $error->getMessage();
        });

        $dbm = container()->get('dbm');
        foreach (range(0, 7) as $id) {
            $sql = "SELECT u.id FROM user_public_info up LEFT JOIN user u ON u.id = up.user_id WHERE u.nick LIKE '%测试%' ORDER BY RAND() LIMIT 1";
            $promise = $dbm->query('vip-music', $sql, 'slave', true);
            $promise->then(function($query_result) {
                echo json_encode($query_result->result, JSON_UNESCAPED_UNICODE) . "\r\n";
                return $query_result;
            }, function ($error){
                echo $error->getMessage() . "\r\n";
            });
            unset($promise);
        }
        $this->memoryWatch();
        echo "started\r\n";
    }

    private function memoryWatch()
    {
        $loop = container()->get('loop');
        $loop->addPeriodicTimer(10, function() {
            $byte = memory_get_peak_usage(true);
            $peak = number_format(bcdiv($byte, 1024, 4), 2) . "KB";
            $byte = memory_get_usage(true);
            $current = number_format(bcdiv($byte, 1024, 4), 2) . "KB";
            $byte = memory_get_usage();
            $php_current = number_format(bcdiv($byte, 1024, 4), 2) . "KB";
            $byte = memory_get_peak_usage(true);
            $php_peak = number_format(bcdiv($byte, 1024, 4), 2) . "KB";
            echo json_encode(['total_peak' => $peak, 'total_current' => $current, 'php_peak' => $php_peak, 'php_current' => $php_current]) . "\r\n";
        });
    }
}