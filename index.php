<?php

class ServerHealth {
    private string $interface;
    private int $maxBandwidth;

    public function __construct() {
        $config = include('config.php');

        $this->interface = $config['interface'] ?? 'default_interface';
        $this->maxBandwidth = $config['max_bandwidth'] ?? 1000000;
    }

    public function getHealthInfo(): array {
        $hostname = $this->getHostname();
        $currentBandwidth = $this->getCurrentBandwidth();
        $averageBandwidthPerMinute = $this->calculateAverageBandwidth($currentBandwidth);

        return [
            'Hostname' => $hostname,
            'MaxBandwidth' => $this->maxBandwidth,
            'CurrentBandwidth' => $currentBandwidth,
            'AverageBandwidthPerMinute' => $averageBandwidthPerMinute,
        ];
    }

    private function getHostname(): string {
        return gethostname();
    }

    private function getCurrentBandwidth(): int {
        // Используем vnstat для получения текущей скорости для заданного интерфейса в формате JSON
        $output = shell_exec("vnstat -i {$this->interface} -tr 5 --json");
        $currentBandwidth = 0;

        // Преобразуем вывод в массив
        if ($output) {
            $data = json_decode($output, true);
            if (isset($data['tx']['bytespersecond'])) {
                $currentBandwidth = (int)$data['tx']['bytespersecond']; // Получаем значение текущей скорости
            }
        }

        // Преобразуем байты в биты в секунду
        return $currentBandwidth * 8; // Умножаем на 8 для получения значения в битах
    }

    private function calculateAverageBandwidth(int $currentBandwidth): int {
        // Получаем данные о трафике за последние минуты с помощью vnstat для заданного интерфейса
        $vnstatOutput = shell_exec("vnstat -i {$this->interface} --fiveminutes --json");    
        $vnstatData = json_decode($vnstatOutput, true);

        // Проверяем, есть ли данные
        if (isset($vnstatData['interfaces']) && !empty($vnstatData['interfaces'])) {
            $interfaceData = $vnstatData['interfaces'][0]; // Предполагаем, что используем первый интерфейс
            $lastMinuteData = $interfaceData['traffic']['fiveminute'] ?? []; // Получаем данные за последние минуты

            // Получаем данные за последнюю минуту
            $lastMinuteTraffic = end($lastMinuteData); // Берем последнюю запись
            $totalBandwidthBytes = $lastMinuteTraffic['tx'];

            // Преобразуем байты в биты и рассчитываем среднюю скорость в битах в секунду
            $averageBandwidthPerSecond = (int)(($totalBandwidthBytes * 8) / 300); // Делим на 300 для получения значения в битах в секунду
        } else {
            // Если данных нет, используем текущую скорость в битах в секунду
            $averageBandwidthPerSecond = $currentBandwidth; 
        }

        return $averageBandwidthPerSecond; // Возвращаем значение в битах в секунду
    }
}

// Устанавливаем заголовок контента на application/json
header('Content-Type: application/json');

// Создаем экземпляр класса и выводим JSON
$health = new ServerHealth();
echo json_encode($health->getHealthInfo());
?>
