<?php
session_save_path('tmp');
session_start();

class lokiSQL {
  private
    $db,
    $prefix_name = '_loki_';

  public function __construct() {
    $this->db = new PDO("mysql:host=127.0.0.1;dbname=project_camp;charset=utf8", "root", "", null);
  }

  public function select($tb, $wh) {
    return $this->db->query("SELECT * FROM " . $this->prefix_name . $tb . " WHERE " . $wh)->fetchAll();
  }

  public function insert($tb, $sqlCode) {  //提供資料表名稱跟value陣列，能操作 SQL-INSERT
    return $this->db->query('INSERT INTO ' . $this->prefix_name . $tb . ' VALUES (' . implode(',', $sqlCode) . ')');
  }

  public function update($tb, $set, $wh) {  //提供資料表名稱跟value陣列，能操作 SQL-INSERT
    return $this->db->query('UPDATE ' . $this->prefix_name . $tb . ' SET ' . $set . ' WHERE ' . $wh);
    // UPDATE _loki_order_list SET del=1 WHERE id=5
  }
}

///////////// custom function
$sql = new lokiSQL();

// function checkUserSaveSession($acc, $pwd) {
//   global $sql;
//   if (isset($_SESSION['admin'])) return true; //如果存在就直接回傳ture，不用再驗證設定SESSION

//   $check = !!$sql->select('user', 'name="' . $acc . '" AND password="' . $pwd . '" AND active=1');
//   if ($check) $_SESSION['admin'] = $acc;
//   return $check;
// }

// var_dump(checkUserSaveSession('admin', '1234'));


function getOrderList() {
  global $sql;
  return $sql->select('order_list', 'del=0');
}

function saveOrder($sqlCode) {
  global $sql;
  return $sql->insert('order_list', $sqlCode)->queryString; //如果SQL指令成功，可以捕獲到這個String
}

function delOrder($id) {
  global $sql;
  return $sql->update('order_list', 'del=1', 'id=' . $id)->queryString;
  // UPDATE _loki_order_list SET del=1 WHERE id=5
}

// api todo
if (isset($_GET['do'])) {
  switch ($_GET['do']) {
    case 'newOrder':

      // var_dump($_POST['sellout']);//注意這裡是字串 string(41) "{"aArea":2,"bArea":0,"cArea":0,"dArea":0}"
      // var_dump($_POST['selectDate']);//這裡也是字串

      // $selectDateAry = json_decode($_POST['selectDate']);
      $selectDateZip = serialize(json_decode($_POST['selectDate']));

      // $selloutAry = json_decode($_POST['sellout'], true);
      // $selloutIsSet = array_filter($selloutAry, function ($v) {
      //   return $v !== 0;
      // });
      $selloutZip = serialize(array_filter(json_decode($_POST['sellout'], true), function ($v) {
        return $v !== 0;
      }));

      $sqlCode = ['null', '\'' . $_POST['userName'] . '\'', '\'' . $_POST['userPhone'] . '\'', '\'' . $_POST['userMail'] . '\'', '\'' . $selectDateZip . '\'', '\'' . $selloutZip . '\'', 'NOW()', 999, 0];
      //最後提交到sql時需要string符號，因此這裡需要追加並利用跳脫字元。
      // print_r($sqlCode);

      // saveOrder($sqlCode);
      if (saveOrder($sqlCode)) {
        header("Content-Type: application/json");
        echo json_encode(['STATE' => 'DONE']); //最後要回應給前端一個json被捕獲。
        exit();
      } else echo 'SQL FAIL';
      break;

    case 'delOrder':
      if (delOrder($_GET['id'])) {
        header('Location:admin.php');
        exit();
      } else echo 'SQL FAIL';
      break;

    default:
      break;
  }
}
