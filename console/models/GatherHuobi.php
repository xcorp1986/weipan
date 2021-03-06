<?php

namespace console\models;

use common\models\Product;

class GatherHuobi extends Gather {

	// 交易产品列表，格式为["表名" => "抓取链接参数名"]
	public $productList = [

	];
	//curl获取数据
	public function curlfun($url, $params = array(), $method = 'GET') {

		$header = array();
		$opts = array(CURLOPT_TIMEOUT => 8, CURLOPT_RETURNTRANSFER => 1, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => false, CURLOPT_HTTPHEADER => $header);

		/* 根据请求类型设置特定参数 */
		switch (strtoupper($method)) {
		case 'GET':
			$opts[CURLOPT_URL] = $url . '?' . http_build_query($params);
			$opts[CURLOPT_URL] = substr($opts[CURLOPT_URL], 0, -1);

			break;
		case 'POST':
			//判断是否传输文件
			$params = http_build_query($params);
			$opts[CURLOPT_URL] = $url;
			$opts[CURLOPT_POST] = 1;
			$opts[CURLOPT_POSTFIELDS] = $params;
			break;
		default:

		}

		/* 初始化并执行curl请求 */
		$ch = curl_init();
		curl_setopt_array($ch, $opts);
		$data = curl_exec($ch);
		$error = curl_error($ch);
		curl_close($ch);

		if ($error) {
			$data = null;
		}

		return $data;

	}
	public function run() {
		$this->switchMap = option('risk_product') ?: [];

		$products = Product::find()->where(['state' => 1, 'on_sale' => 1, 'source' => 1])->select('table_name, code, trade_time, id')->asArray()->all();

		// var_dump(json_encode($products));

		// exit();

		$this->productList = array_merge($this->productList, $products);

		foreach ($this->productList as $tableName => $info) {
			//比特币15分钟
			if ($info['code'] == "btc") {
				$url = 'https://www.bitstamp.net/api/v2/ticker/btcusd?time='.time();
				$result = $this->curlfun($url);
				$resultarr = explode(',', $result);
				$arr = array();
				for ($i = 0; $i < count($resultarr); $i++) {
					$arr[$i] = trim(str_replace('"', '', explode(':', $resultarr[$i])[1]));
				}

				$price = $arr[3];
				$diff = $price - $arr[3];
				if ($diff == 0) {
					$diff_rate = 0.00;
				} else {
					$diff_rate = number_format($diff / $arr[3] * 100, 2, ".", "");
				}

				// echo $resultarr[sizeof($resultarr) - 2] ." " .explode('"', $resultarr[sizeof($resultarr) - 1])[0];
//    $dtime = strtotime(explode('"', $arr[sizeof($arr) - 1])[0] . " " . explode('"', $arr[0])[1]);
				// echo date('Y-m-d H:i:s', $dtime);
				$data = [
					'price' => $price,
					'open' => $arr[8],//
					'high' => $arr[0],//
					'low' => $arr[6],//
					'close' => $arr[3],
					'diff' => $diff,
					'diff_rate' => $diff_rate,
					'time' => date('Y-m-d H:i:s', $arr[2])//
				];

				file_put_contents('btc.txt', json_encode($data), FILE_APPEND);
				$this->insert($info['table_name'], $data);
			}
			}
			if ($info['code'] == "ltc") {

				$url = "https://api.huobipro.com/market/history/kline?period=1min&size=1&symbol=ltcusdt";
				$getdata = $this->curlfun($url);

				$getdata = json_decode($getdata, true)['data'][0];

				$thisdata['Price'] = $getdata['close'];
				$thisdata['Open'] = $getdata['open'];
				$thisdata['Close'] = $getdata['close'];
				$thisdata['High'] = $getdata['high'];
				$thisdata['Low'] = $getdata['low'];
				$thisdata['Diff'] = 0;
				$thisdata['DiffRate'] = 0;

				$data = [
					'price' => $getdata['close'],
					'open' => $getdata['open'],
					'high' => $getdata['high'],
					'low' => $getdata['low'],
					'close' => $getdata['close'],
					'diff' => number_format($getdata['close'] - $getdata['open'], 2),
					'diff_rate' => number_format(($getdata['close'] - $getdata['open']) / $getdata['open'] * 100, 2, ".", ""),
					'time' => date('Y-m-d H:i:s'),
				];
				file_put_contents('ltc.txt', json_encode($data), FILE_APPEND);
				$this->insert($info['table_name'], $data);
			}


		// 更新 data_all 的最新价格
		// foreach ($this->updateMap as $key => $value) {
		//     $value['diff'] = sprintf('%.2f', $value['diff']);
		//     self::dbUpdate('data_all', ['price' => $value['price'], 'time' => $value['time'], 'diff' => $value['diff'], 'diff_rate' => $value['diff_rate']], ['name' => $key]);
		// }
		// 监听是否有人应该平仓
		$this->listen();
	}

}
