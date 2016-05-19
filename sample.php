<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once("fannWrapper.php");


// ##############################
// ### 学習範囲
// 0から790日目までで任意の範囲
	$start = 250;
	$stop = 350;
// ### ニューラルネットワークの層構造の指定
// 入力層は7、出力層は1
	$layers = [5, 10, 3, 1];
// ### 正規化の範囲
// 活性化関数に***_SYMMETRICを指定する場合は1から-1でそれ以外は1から0
	$range = array("max" => 1.0, "min" => -1.0);
// ### テスト配列
// 直近5日間のドル円レート
	$test = [100.29, 101.40, 101.11, 100.97, 101.03];
// ##############################


// FANNラッパークラス
$ann = new fannWrapper($layers);

// データを読み込み
$json = file_get_contents(__DIR__ . "/data/usdjpy.json");
$data = json_decode($json, true);

// 与えられた範囲を抜き出して数値のみの配列をつくる
$usdjpy = [];
for ($i=$start; $i < $stop; $i++) {
	if (!isset($data[$i])) break;
	$usdjpy[] = (float)$data[$i]["rate"];
}

// 配列の統計情報を連想配列で返す
$stats = fannWrapper::stats($usdjpy);

// 正規化
$usdjpy = fannWrapper::scaling($usdjpy, $range["max"], $range["min"]);

foreach ($usdjpy as $k => $v) {
	if (!isset($usdjpy[$k+5])) break;
	// 入力配列
	$input = [$usdjpy[$k], $usdjpy[$k+1], $usdjpy[$k+2], $usdjpy[$k+3], $usdjpy[$k+4]];
	// 出力配列
	$output = [$usdjpy[$k+5]];
	// 学習実行
	$ann->train($input, $output);
}

// 学習結果を保存
$ann->save(__DIR__ . "/data/usdjpy.net");

// 予測テスト実行
// テストデータは実世界のものなので、教育したデータにあわせて正規化して入力
$test_n = [];
for ($i=0; $i < count($test); $i++) {
	$test_n[$i] = fannWrapper::scaling([$test[$i]], $range["max"], $range["min"], $stats["max"], $stats["min"]);
}
$result = fannWrapper::scaling($ann->run($test_n), $stats["max"], $stats["min"], $range["max"], $range["min"]);

// canvas上でネットワークを視覚的に描画するためにノードの配置を取得する
$canvasX = 800; $canvasY = 450;
$neuron_position = json_encode($ann->visualize($canvasX, $canvasY, 50));
$neuron_connection = json_encode($ann->getConnection());

?>
<!DOCTYPE html>
<html>

<head>
<title>PHP FANN Sample</title>
<meta charset="utf-8">
<script type="text/javascript" src="jquery-1.9.1.min.js"></script>
<script src="flotr2.min.js"></script>
<script type="text/javascript">
onload = function() {
	draw();
};
function draw() {
	var neuronInfo = <?php echo $neuron_position;?>;
	var neuronConn = <?php echo $neuron_connection;?>;
	var canvas = document.getElementById("fannImage");
	if (canvas.getContext) {
		var cv = canvas.getContext("2d");
		cv.lineWidth = 1;

		// グラフ描画
		for (var i = 0; i < neuronConn.length; i++) {
			var ff = neuronConn[i]["from_neuron"];
			var tt = neuronConn[i]["to_neuron"];
			var csx = neuronInfo[ff][0];
			var csy = neuronInfo[ff][1];
			var cex = neuronInfo[tt][0];
			var cey = neuronInfo[tt][1];
			var ww = neuronConn[i]["weight"];
			if (ww <= 0.0) {
				var cc = (-100) * ww;
				cv.fillStyle = "rgb(cc, cc, cc)";
				cv.lineWidth = 1;
			} else {
				cv.fillStyle = "rgb(0, 0, 0)";
				cv.lineWidth = (neuronConn[i]["weight"]) * 10;
			}
			cv.beginPath();
			cv.moveTo(csx, csy);
			cv.lineTo(cex, cey);
			cv.closePath();
			cv.stroke();
		}

		// ニューロン描画
		for (var i = 0; i < neuronInfo.length; i++) {
			var x = neuronInfo[i][0];
			var y = neuronInfo[i][1];
			cv.beginPath();
			switch (neuronInfo[i][2]) {
				case "input":
					cv.fillStyle = "rgb(255, 100, 200)";
					break;
				case "output":
					cv.fillStyle = "rgb(255, 0, 0)";
					break;
				case "input_bias":
					cv.fillStyle = "rgb(100, 255, 100)";
					break;
				case "hidden_bias":
					cv.fillStyle = "rgb(150, 150, 255)";
					break;
				default:
					cv.fillStyle = "rgb(0, 0, 0)";
			}
			cv.arc(x, y, 10, 0, 2*Math.PI, true);
			cv.fill();
		}
	}
}
</script>

<style type="text/css">
	div {margin: 20px;}
	canvas {border: 2px solid #555555;}
	table,td,th {border: 1px solid black;}
	#learnGraph {width: 800px; height: 450px;}
	#fxGraph {width: 800px; height: 450px;}
</style>
</head>


<body>
	<div>
		<canvas id="fannImage" width="<?php echo $canvasX ?>" height="<?php echo $canvasY ?>"></canvas>
	</div>

	<div id="learnGraph">
		<script type="text/javascript">
		$(function gr(container){
			var d1, d2, options, graph;
			d1 = [ <?php foreach ($ann->getMSEarray() as $k => $v) {echo "[".$k.",".$v."],";} ?> ];
			d2 = [ <?php foreach (fannWrapper::scaling($usdjpy, $stats["max"], $stats["min"], 1.0, -1.0) as $k => $v) {echo "[".$k.",".$v."],";} ?> ];
			options = {
				title: " ",
				HtmlText: false,
				xaxis: { mode: 'normal', timeMode: 'local', title: "Learning samples", labelsAngle : 0, autoscale: true },
				selection : { mode: 'x', fps: 30 },
				yaxis: { title: "MSE", autoscale: true },
				y2axis: { title: "Rate", autoscale: true },
				grid: { labelMargin: 10 },
				legend: { position: "ne" }
			};
			function drawGraph (opts) {
				var o = Flotr._.extend(Flotr._.clone(options), opts || {});
				return Flotr.draw($('#learnGraph').get(0), [ {data: d1, label: "MSE transition", yaxis: 1}, {data: d2, label: "USDJPY", yaxis: 2} ], o);
			};
			graph = drawGraph();
			Flotr.EventAdapter.observe($('#learnGraph').get(0), 'flotr:select', function (area) {
				graph = drawGraph({
					HtmlText: false,
					xaxis: { min: area.x1, max: area.x2, mode: 'normal', timeMode: 'local', title: "Learning samples", labelsAngle : 0, autoscale: true },
					yaxis: { min: area.y1, max: area.y2, title: "MSE", autoscale: true },
					y2axis: { min: null, max: null, title: "Rate", autoscale: true }
				});
			});
			Flotr.EventAdapter.observe($('#learnGraph').get(0), 'flotr:click', function () { graph = drawGraph(); });
		});
		</script>
	</div>

	<div>
		<table>
			<tr><th>MSE Final</th><td><?php echo $ann->getMSE() ?></td></tr>
			<tr><th>Estimated Rate</th><td><?php echo $result[0] ?></td></tr>
		</table>
		<br>
		<table>
			<tr><th>Learning Data</th><td>USD / JPY</td></tr>
			<?php foreach ($stats as $k => $v){
				echo "<tr><th>".$k."</th><td>".$v."</td></tr>\n";
			}?>
		</table>
	</div>
</body>
</html>
