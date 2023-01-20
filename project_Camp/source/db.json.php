<?php
require_once("./function.php");

$dbJSONStr = '{
  "success": true,
  "nationalHoliday": [
    "2023-01-02",
    "2023-01-20",
    "2023-01-23",
    "2023-01-24",
    "2023-01-25",
    "2023-01-26",
    "2023-01-27",
    "2023-02-27",
    "2023-02-28",
    "2023-04-03",
    "2023-04-04",
    "2023-04-05",
    "2023-06-22",
    "2023-06-23",
    "2023-09-29",
    "2023-10-09",
    "2023-10-10",
    "2024-01-01"
  ],
  "pallet": {
    "count": 40,
    "aArea": {
      "total": 10,
      "normalPrice": 1000,
      "holidayPrice": 1500
    },
    "bArea": {
      "total": 10,
      "normalPrice": 1100,
      "holidayPrice": 1600
    },
    "cArea": {
      "total": 10,
      "normalPrice": 1200,
      "holidayPrice": 1700
    },
    "dArea": {
      "total": 10,
      "normalPrice": 2000,
      "holidayPrice": 2500
    }
  },
  "booked": [
    {
      "date": "2022-12-12",
      "sellout": {
        "aArea": 1,
        "bArea": 1,
        "cArea": 1,
        "dArea": 1
      }
    },
    {
      "date": "2022-12-13",
      "sellout": {
        "aArea": 2,
        "bArea": 2,
        "cArea": 10,
        "dArea": 2
      }
    },
    {
      "date": "2022-12-14",
      "sellout": {
        "aArea": 3,
        "bArea": 3,
        "cArea": 3,
        "dArea": 3
      }
    },
    {
      "date": "2022-12-25",
      "sellout": {
        "aArea": 10,
        "bArea": 10,
        "cArea": 10,
        "dArea": 10
      }
    },
    {
      "date": "2022-12-16",
      "sellout": {
        "aArea": 10,
        "bArea": 10,
        "cArea": 10,
        "dArea": 10
      }
    }
  ]
}';


// pallet start
$rows = getPallet(); //取得
$pallet = []; //初始
foreach ($rows as $row) {
  $pallet += [$row['name'] => [
    'total' => $row['total'],
    'normalPrice' => $row['normalPrice'],
    'holidayPrice' => $row['holidayPrice'],
  ]];
}
$pallet += ['count' => array_sum(array_column($rows, 'total'))]; //計算count的value

$dbJSONAry = json_decode($dbJSONStr, true); 
$dbJSONAry['pallet'] = $pallet; //更新指定的值

header("Content-Type: application/json"); //將網頁的內容型別切成json，讓瀏覽器知道這是json不是string
echo json_encode($dbJSONAry); //將php的經修改的array以json(string)方式顯示。