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
    // echo 'UPDATE ' . $this->prefix_name . $tb . ' SET ' . $set . ' WHERE ' . $wh;
    return $this->db->query('UPDATE ' . $this->prefix_name . $tb . ' SET ' . $set . ' WHERE ' . $wh);
    // UPDATE _loki_order_list SET del=1 WHERE id=5
  }

  public function query($sqlCode) {  //提供資料表名稱跟value陣列，能操作 SQL-INSERT
    return $this->db->query($sqlCode);
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
  // print_r($_GET);
  global $sql;

  foreach ($_GET['date'] as $date) {
    // UPDATE _loki_daily_state SET aArea = aArea + 1, bArea=bArea+2, cArea=cArea+3 WHERE date = '2023-02-01'
    $areaUpdate = implode(', ', array_map(
      function ($value, $key) {
        return '' . $key . ' = ' . $key . ' - ' . $value; //aArea = aArea + 1
      },
      $_GET['pallet'],
      array_keys($_GET['pallet'])
    ));
    // echo 'UPDATE _loki_daily_state SET ' . $areaUpdate . ' WHERE date = "' . $date . '"';
    $sql->query('UPDATE _loki_daily_state SET ' . $areaUpdate . ' WHERE date = "' . $date . '"');
  }

  return $sql->update('order_list', 'del=1', 'id=' . $id)->queryString;
  // UPDATE _loki_order_list SET del=1 WHERE id=5
}

function getPallet() {
  global $sql;
  return $sql->select('pallet', 1);
}

function updatePallet($id, $set) {
  global $sql;
  return $sql->update('pallet', $set, 'id=' . $id)->queryString;
}

function getHoliday() {
  global $sql;

  // INSERT INTO _loki_holiday (year) SELECT YEAR(CURRENT_DATE())+3 WHERE NOT EXISTS (SELECT * FROM _loki_holiday WHERE year=YEAR(CURRENT_DATE())+3)
  for ($i = 0; $i < 3; $i++) {
    $checkYear = 'INSERT INTO _loki_holiday (year) SELECT YEAR(CURRENT_DATE())+' . $i . ' WHERE NOT EXISTS (SELECT * FROM _loki_holiday WHERE year=YEAR(CURRENT_DATE())+' . $i . ')';
    $sql->query($checkYear);
  }
  return $sql->select('holiday', 'year>=YEAR(CURRENT_DATE()) ORDER BY year');
}

function updateHoliday($id, $set) {
  global $sql;
  return $sql->update('holiday', $set, 'id=' . $id)->queryString;
}

function getDaily() {
  global $sql;
  return $sql->select('daily_state', 'date>=DATE(CURRENT_DATE()) ORDER BY date');
}

function getDaily_table() {
  $dailyDB = getDaily();
  $palletDB = getPallet();
  $namePrice = [];
  foreach ($palletDB as $row)
    $namePrice[$row['name']] = [$row['normalPrice'], $row['holidayPrice']];

  $table_head = array_column($palletDB, 'total');
  $table_body = array_map(function ($row) use ($namePrice) {
    $row['holiday'] = checkHoliday($row['date']);
    // $price = +checkHoliday($row['date']);
    $row['total'] =
      $row['aArea'] * $namePrice['aArea'][+$row['holiday']] +
      $row['bArea'] * $namePrice['bArea'][+$row['holiday']] +
      $row['cArea'] * $namePrice['cArea'][+$row['holiday']] +
      $row['dArea'] * $namePrice['dArea'][+$row['holiday']];
    return $row;
  }, $dailyDB);

  return [$table_head, $table_body];
}

function checkHoliday($date) {
  $holidayAry = [];
  foreach (getHoliday() as $row)
    $holidayAry = array_merge($holidayAry, explode("\r\n", $row['date']));

  $day = date("D", strtotime($date));
  return $day == 'Sat' || $day == 'SUN' || in_array($date, $holidayAry) ? true : false;
}


// api todo
if (isset($_GET['do'])) {
  switch ($_GET['do']) {
    case 'newOrder':
      // 前端payload資料 
      // userName: 假日測試
      // userPhone: 555
      // userMail: 555@55
      // selectDate: ["2023-02-25","2023-02-26","2023-02-27"]
      // sellout: {"aArea":0,"bArea":0,"cArea":2,"dArea":0}

      $selectDateAry = json_decode($_POST['selectDate']); //日期陣列
      $selloutAry = array_filter(json_decode($_POST['sellout'], true), function ($v) { //購買陣列
        return $v !== 0;
      });

      // 設計價目表
      $palletAry = [];
      foreach (getPallet() as $row) {
        $palletAry[$row['name']]['normalPrice'] = $row['normalPrice'];
        $palletAry[$row['name']]['holidayPrice'] = $row['holidayPrice'];
        $palletAry[$row['name']]['total'] = $row['total']; // 1-1. 追加
      }

      global $sql;
      $sum = 0; //總價計算前置準備
      foreach ($selectDateAry as $date) {
        ///////// start
        //1. 組合出檢查代碼，如果有結果代表爆掉了，就阻擋建立動作並離開php
        //SELECT * FROM `_loki_daily_state` WHERE date='2023-02-01' AND (aArea + 9 > 10 OR bArea + 9 > 10)
        $areaCheck = implode(' OR ', array_map(
          function ($value, $key) use ($palletAry) {
            return $key . ' + ' . $value . ' > ' . $palletAry[$key]['total']; //aArea + 5 > 10
          },
          $selloutAry,
          array_keys($selloutAry)
        ));

        // $resultCheck = $sql->select('daily_state', 'date="' . $date . '" AND (' . $areaCheck . ')');
        if ($sql->select('daily_state', 'date="' . $date . '" AND (' . $areaCheck . ')')) {
          echo 'PALLET FULL';
          exit();
        }

        // 2. 試著將不存在的日期補足
        // INSERT INTO _loki_daily_state (date) SELECT '2023-03-01' WHERE NOT EXISTS (SELECT * FROM _loki_daily_state WHERE date='2023-03-01')
        $checkYear = 'INSERT INTO _loki_daily_state (date) SELECT "' . $date . '" WHERE NOT EXISTS (SELECT * FROM _loki_daily_state WHERE date="' . $date . '")';
        $sql->query($checkYear);


        // 3. 翻新 _loki_daily_state 用，組合出 SET 代碼
        // UPDATE _loki_daily_state SET aArea = aArea + 1, bArea=bArea+2, cArea=cArea+3 WHERE date = '2023-02-01'
        $areaUpdate = implode(', ', array_map(
          function ($value, $key) {
            return '' . $key . ' = ' . $key . ' + ' . $value; //aArea = aArea + 1
          },
          $selloutAry,
          array_keys($selloutAry)
        ));
        $sql->query('UPDATE _loki_daily_state SET ' . $areaUpdate . ' WHERE date = "' . $date . '"');
        ///////// end

        foreach ($selloutAry as $key => $value) {
          // 每日每營位的總價疊加，value = 數量
          $sum += $palletAry[$key][checkHoliday($date) ? 'holidayPrice' : 'normalPrice'] * $value;
        }
      }

      //訂單資料
      $selectDateZip = serialize($selectDateAry); //提交用
      $selloutZip = serialize($selloutAry); //提交用
      $sqlCode = ['null', '\'' . $_POST['userName'] . '\'', '\'' . $_POST['userPhone'] . '\'', '\'' . $_POST['userMail'] . '\'', '\'' . $selectDateZip . '\'', '\'' . $selloutZip . '\'', 'NOW()', $sum, 0];

      //新增訂單至SQL
      if (saveOrder($sqlCode)) {
        header("Content-Type: application/json");
        echo json_encode(['STATE' => 'DONE']); //最後要回應給前端一個json被捕獲。
      } else echo 'SQL FAIL';

      exit();
      break;

    case 'delOrder':
      if (delOrder($_GET['id'])) {
        header('Location:admin.php');
        exit();
      } else echo 'SQL FAIL';
      break;

    case 'mdyPallet':
      // print_r($_POST);
      $flag = true;
      foreach ($_POST['id'] as $key => $value) {
        // "UPDATE _loki_pallet SET id=[value-1],name=[value-2],total=[value-3],normalPrice=[value-4],holidayPrice=[value-5] WHERE 1";
        $setAry = [
          'total=' . $_POST['total'][$key],
          'normalPrice=' . $_POST['normalPrice'][$key],
          'holidayPrice=' . $_POST['holidayPrice'][$key]
        ];

        $setStr = implode(', ', $setAry);
        if (!updatePallet($value, $setStr)) $flag = false;
      }

      if ($flag) {
        header('Location:pallet.php');
        exit();
      }
      break;
    case 'mdyHoliday':
      // print_r($_POST);
      $flag = true;
      foreach ($_POST['id'] as $key => $value) {
        $setStr = 'date=\'' . $_POST['date'][$key] . '\'';
        if (!updateHoliday($value, $setStr)) $flag = false;
      }

      if ($flag) {
        header('Location:holiday.php');
        exit();
      }
      break;
    default:
      break;
  }
}
