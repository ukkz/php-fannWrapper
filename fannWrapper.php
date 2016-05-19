<?php

// A Wrapper Class for Fast Artificial Neural Network

class fannWrapper
{
    private $ann;
    private $trainedfile;
    private $msetrans = array();

    public function __construct(array $layers)
    {
        $this->ann = fann_create_standard_array(count($layers), $layers);
        fann_set_training_algorithm($this->ann, FANN_TRAIN_RPROP);
        fann_set_activation_function_hidden($this->ann, FANN_ELLIOT_SYMMETRIC);
        fann_set_activation_function_output($this->ann, FANN_ELLIOT_SYMMETRIC);
        $this->resetMSE();
    }

    public function __destruct() {fann_destroy($this->ann);}

    public function save($file = "")
    {
        $this->trainedfile = $file;
        return fann_save($this->ann, $file);
    }

    public function run(array $data) {return fann_run($this->ann, $data);}

    public function getResource() {return $this->ann;}

    public function getMSE() {return fann_get_MSE($this->ann);}

    public function getMSEarray() {return $this->msetrans;}

    public function getConnection() {return fann_get_connection_array($this->ann);}

    public function train(array $input, array $output) {
        $r = fann_train($this->ann, $input, $output);
        $this->msetrans[] = $this->getMSE();
        return $r;
    }

    public function resetMSE() {
        $r = fann_reset_MSE($this->ann);
        $this->msetrans = array();
        return $r;
    }

    public function visualize($canvasX, $canvasY, $padding)
    {
        $layers = fann_get_num_layers($this->ann);
        $ar = fann_get_layer_array($this->ann);
        $bi = fann_get_bias_array($this->ann);
        $maxSizeLayer = max($ar);
        $startXline = $padding;
        $xLine = $startXline;
        $endXline = $canvasX - $padding;
        $startYline = $padding;
        $yLine = $startYline;
        $endYline = $canvasY - $padding;
        $layersSpan = ($endXline - $xLine) / ($layers - 1);
        $neuronsSpan = ($endYline - $yLine) / $maxSizeLayer;
        $nInfo = [];
        // each layers
        foreach ($ar as $k => $v) {
            if ($maxSizeLayer != $v) {
                $yLine = $startYline + (($maxSizeLayer-$v)*$neuronsSpan/2);
            }
            // normal
            for ($i=0; $i < $v; $i++) {
                switch ($k) {
                    case 0:
                        $nInfo[] = [$xLine, $yLine, "input"];
                        break;
                    case $layers-1:
                        $nInfo[] = [$xLine, $yLine, "output"];
                        break;
                    default:
                        $nInfo[] = [$xLine, $yLine, "hidden"];
                        break;
                }
                $yLine += $neuronsSpan;
            }
            // bias
            for ($i=0; $i < $bi[$k]; $i++) {
                switch ($k) {
                    case 0:
                        $nInfo[] = [$xLine, $yLine, "input_bias"];
                        break;
                    case $layers-1:
                        $nInfo[] = [$xLine, $yLine, "output_bias"];
                        break;
                    default:
                        $nInfo[] = [$xLine, $yLine, "hidden_bias"];
                        break;
                }
                $yLine += $neuronsSpan;
            }
            $yLine = $startYline;
            $xLine += $layersSpan;
        }
        return $nInfo;
    }




    // ##############################
    // ### Utility static methods
    // ##############################

    // Normalization (scale)
    public static function scaling(array $values, $output_max = 1.0, $output_min = -1.0, $input_max = null, $input_min = null, $rounding = false)
    {
        if (!is_null($input_max) and !is_null($input_min)) {
            $input_range = (float)($input_max - $input_min);
            $offset = (float)$input_min;
        } else {
            $input_range = (float)(max($values) - min($values));
            $offset = (float)min($values);
        }

        $output_range = (float)($output_max - $output_min);
        $mag = (float)($output_range/$input_range);

        $scaled = array();
        foreach ($values as $v) {
            if ($v > $input_max & $rounding) $v = $input_max;
            if ($v < $input_min & $rounding) $v = $input_min;
            $scaled[] = (float)(($v - $offset) * $mag) + $output_min;
        }
        return $scaled;
    }

    // Normalization (standard)
    public static function standardize(array $values)
    {
        $stat = self::stats($values);
        $standardized = array();
        foreach ($values as $v) {
            $standardized[] = (float)($v - $stat["average"]) / $stat["standard_deviation"];
        }
        return $standardized;
    }

    // Statistics Infomation
    public static function stats(array $values)
    {
        $statsInfo = array();
        $statsInfo["count"] = count($values);
        $statsInfo["max"] = max($values);
        $statsInfo["min"] = min($values);
        $statsInfo["average"] = (float)(array_sum($values) / $statsInfo["count"]);
        $variance = 0.0;
        foreach ($values as $v) {
            $variance += pow($v - $statsInfo["average"], 2);
        }
        sort($values);
        if ($statsInfo["count"] % 2 == 0) {
            $statsInfo["median"] = (($values[($statsInfo["count"] / 2) - 1] + $values[(($statsInfo["count"] / 2))]) / 2);
        } else {
            $statsInfo["median"] = ($values[floor($statsInfo["count"] / 2)]);
        }
        $statsInfo["variance"] = (float)($variance / $statsInfo["count"]);
        $statsInfo["variance_unbiased"] = (float)($variance / $statsInfo["count"] - 1);
        $statsInfo["standard_deviation"] = (float)sqrt($statsInfo["variance"]);
        return $statsInfo;
    }
}
