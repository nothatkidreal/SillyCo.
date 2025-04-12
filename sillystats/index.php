<?php
header('Content-Type: application/json');

// Initialize the result array
$result = array();

// Get the script start time
$script_start_time = microtime(true);

// Helper function to format bytes
function format_bytes($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    
    $bytes /= pow(1024, $pow);
    
    return round($bytes, $precision) . ' ' . $units[$pow];
}

// New helper function to format uptime
function format_uptime($uptime_str) {
    // Different patterns to match various uptime formats
    if (preg_match('/up\s+(\d+)\s+days?,\s+(\d+):(\d+)/', $uptime_str, $matches)) {
        $days = $matches[1];
        $hours = $matches[2];
        $minutes = $matches[3];
        return "$days days, $hours hours and $minutes minutes";
    } elseif (preg_match('/up\s+(\d+):(\d+)/', $uptime_str, $matches)) {
        $hours = $matches[1];
        $minutes = $matches[2];
        return "$hours hours and $minutes minutes";
    } elseif (preg_match('/up\s+(\d+)\s+min/', $uptime_str, $matches)) {
        $minutes = $matches[1];
        return "$minutes minutes";
    } elseif (preg_match('/up\s+(\d+)\s+day/', $uptime_str, $matches)) {
        $days = $matches[1];
        return "$days days";
    } elseif (preg_match('/up\s+(\d+)\s+days?,\s+(\d+)\s+min/', $uptime_str, $matches)) {
        $days = $matches[1];
        $minutes = $matches[2];
        return "$days days and $minutes minutes";
    } elseif (preg_match('/up\s+(\d+)\s+days?,\s+(\d+)\s+hour/', $uptime_str, $matches)) {
        $days = $matches[1];
        $hours = $matches[2];
        return "$days days and $hours hours";
    }
    
    // Alternative method using /proc/uptime if available
    if (is_readable('/proc/uptime')) {
        $uptime = file_get_contents('/proc/uptime');
        $uptime = explode(' ', $uptime)[0];
        $uptime = (int)$uptime;
        
        $days = floor($uptime / 86400);
        $hours = floor(($uptime % 86400) / 3600);
        $minutes = floor(($uptime % 3600) / 60);
        
        return "$days days, $hours hours and $minutes minutes";
    }
    
    return "Unknown";
}

// 1. Measure time to solve equations
function measure_equation_solving_time($count = 10000) {
    $start_time = microtime(true);
    // Perform a mix of mathematical operations to simulate equation solving
    for ($i = 0; $i < $count; $i++) {
        $x = rand(1, 1000) / 23;
        $y = rand(1, 1000) / 7;
        $result = sqrt(pow($x, 2) + pow($y, 2)) * sin($x) * cos($y) * log(max(1, $x * $y));
        $complex = tan($x) * exp(-1 * abs($y / 100)) / (1 + pow($x/100, 2));
        $result += $complex;
    }
    $end_time = microtime(true);
    return round(($end_time - $start_time) * 1000, 2); // Return time in milliseconds, rounded to 2 decimal places
}

// 2. System memory information
function get_memory_info() {
    $memory_info = array();
    
    // Get PHP memory usage
    $memory_info['php_memory_usage'] = array(
        'current' => memory_get_usage(false),
        'current_formatted' => format_bytes(memory_get_usage(false)),
        'peak' => memory_get_peak_usage(false),
        'peak_formatted' => format_bytes(memory_get_peak_usage(false)),
        'current_real' => memory_get_usage(true),
        'current_real_formatted' => format_bytes(memory_get_usage(true)),
        'peak_real' => memory_get_peak_usage(true),
        'peak_real_formatted' => format_bytes(memory_get_peak_usage(true))
    );
    
    // Try to get system memory info on Linux systems
    if (function_exists('shell_exec') && is_readable('/proc/meminfo')) {
        $meminfo = @shell_exec('cat /proc/meminfo');
        if ($meminfo) {
            $meminfo_array = array();
            foreach (explode("\n", $meminfo) as $line) {
                if (preg_match('/^(\w+):\s+(\d+)\s+kB$/i', $line, $matches)) {
                    $meminfo_array[$matches[1]] = $matches[2] * 1024; // Convert to bytes
                }
            }
            
            if (isset($meminfo_array['MemTotal']) && isset($meminfo_array['MemFree'])) {
                $memory_info['system'] = array(
                    'total' => $meminfo_array['MemTotal'],
                    'total_formatted' => format_bytes($meminfo_array['MemTotal']),
                    'free' => $meminfo_array['MemFree'],
                    'free_formatted' => format_bytes($meminfo_array['MemFree']),
                    'used' => $meminfo_array['MemTotal'] - $meminfo_array['MemFree'],
                    'used_formatted' => format_bytes($meminfo_array['MemTotal'] - $meminfo_array['MemFree']),
                    'used_percent' => round(($meminfo_array['MemTotal'] - $meminfo_array['MemFree']) / $meminfo_array['MemTotal'] * 100, 2)
                );
                
                if (isset($meminfo_array['Cached'])) {
                    $memory_info['system']['cached'] = $meminfo_array['Cached'];
                    $memory_info['system']['cached_formatted'] = format_bytes($meminfo_array['Cached']);
                }
                
                // Add swap information
                if (isset($meminfo_array['SwapTotal']) && isset($meminfo_array['SwapFree'])) {
                    $memory_info['swap'] = array(
                        'total' => $meminfo_array['SwapTotal'],
                        'total_formatted' => format_bytes($meminfo_array['SwapTotal']),
                        'free' => $meminfo_array['SwapFree'],
                        'free_formatted' => format_bytes($meminfo_array['SwapFree']),
                        'used' => $meminfo_array['SwapTotal'] - $meminfo_array['SwapFree'],
                        'used_formatted' => format_bytes($meminfo_array['SwapTotal'] - $meminfo_array['SwapFree']),
                        'used_percent' => $meminfo_array['SwapTotal'] > 0 ? 
                            round(($meminfo_array['SwapTotal'] - $meminfo_array['SwapFree']) / $meminfo_array['SwapTotal'] * 100, 2) : 0
                    );
                }
                
                // Add buffer information
                if (isset($meminfo_array['Buffers'])) {
                    $memory_info['system']['buffers'] = $meminfo_array['Buffers'];
                    $memory_info['system']['buffers_formatted'] = format_bytes($meminfo_array['Buffers']);
                }
            }
        }
    }
    
    return $memory_info;
}

// 3. CPU information
function get_cpu_info() {
    $cpu_info = array();
    
    // Try to get CPU info on Linux systems
    if (function_exists('shell_exec')) {
        // Get CPU model
        $cpu_model = @trim(shell_exec("cat /proc/cpuinfo | grep 'model name' | head -1 | cut -d ':' -f2"));
        if ($cpu_model) {
            $cpu_info['model'] = $cpu_model;
        }
        
        // Get number of cores
        $cpu_cores = (int)@trim(shell_exec("cat /proc/cpuinfo | grep processor | wc -l"));
        if ($cpu_cores) {
            $cpu_info['cores'] = $cpu_cores;
        }
        
        // Get CPU load
        $load = @sys_getloadavg();
        if ($load) {
            $cpu_info['load'] = array(
                '1min' => $load[0],
                '5min' => $load[1],
                '15min' => $load[2]
            );
            
            // Calculate CPU usage percentage based on load average and number of cores
            if (isset($cpu_info['cores']) && $cpu_info['cores'] > 0) {
                $cpu_info['usage_percent'] = round(($load[0] / $cpu_info['cores']) * 100, 2);
            }
        }
        
        // Get CPU frequency
        $cpu_freq = @shell_exec("cat /proc/cpuinfo | grep 'cpu MHz' | head -1 | cut -d ':' -f2");
        if ($cpu_freq) {
            $cpu_info['frequency_mhz'] = (float)trim($cpu_freq);
        }
        
        // Get CPU temperature (if available)
        $cpu_temp = @shell_exec("cat /sys/class/thermal/thermal_zone*/temp 2>/dev/null | head -1");
        if ($cpu_temp && is_numeric(trim($cpu_temp))) {
            $temp_celsius = round(intval(trim($cpu_temp)) / 1000, 1);
            $cpu_info['temperature'] = array(
                'celsius' => $temp_celsius,
                'fahrenheit' => round(($temp_celsius * 9/5) + 32, 1)
            );
        }
        
        // Get detailed CPU stats (newer systems with mpstat)
        $mpstat = @shell_exec("command -v mpstat >/dev/null 2>&1 && mpstat 1 1 | tail -n 1");
        if ($mpstat) {
            $stats = preg_split('/\s+/', trim($mpstat));
            if (count($stats) >= 12) {
                $cpu_info['detailed_stats'] = array(
                    'user' => isset($stats[3]) ? (float)$stats[3] : null,
                    'nice' => isset($stats[4]) ? (float)$stats[4] : null,
                    'system' => isset($stats[5]) ? (float)$stats[5] : null,
                    'iowait' => isset($stats[6]) ? (float)$stats[6] : null,
                    'irq' => isset($stats[7]) ? (float)$stats[7] : null,
                    'soft' => isset($stats[8]) ? (float)$stats[8] : null,
                    'steal' => isset($stats[9]) ? (float)$stats[9] : null,
                    'guest' => isset($stats[10]) ? (float)$stats[10] : null,
                    'idle' => isset($stats[11]) ? (float)$stats[11] : null
                );
            }
        }
    }
    
    return $cpu_info;
}

// 4. Disk space information
function get_disk_info() {
    $disk_info = array();
    
    $total_space = @disk_total_space('.');
    $free_space = @disk_free_space('.');
    
    if ($total_space && $free_space) {
        $used_space = $total_space - $free_space;
        
        $disk_info = array(
            'total' => $total_space,
            'total_formatted' => format_bytes($total_space),
            'free' => $free_space,
            'free_formatted' => format_bytes($free_space),
            'used' => $used_space,
            'used_formatted' => format_bytes($used_space),
            'used_percent' => round($used_space / $total_space * 100, 2)
        );
    }
    
    // Get detailed disk information for each mount point
    if (function_exists('shell_exec')) {
        $df_output = @shell_exec('df -P 2>/dev/null');
        if ($df_output) {
            $lines = explode("\n", trim($df_output));
            $disk_info['mounts'] = array();
            
            // Skip header line
            for ($i = 1; $i < count($lines); $i++) {
                $parts = preg_split('/\s+/', trim($lines[$i]));
                if (count($parts) >= 6) {
                    $mount_point = $parts[5];
                    $disk_info['mounts'][$mount_point] = array(
                        'device' => $parts[0],
                        'total' => intval($parts[1]) * 1024, // Convert from KB to bytes
                        'total_formatted' => format_bytes(intval($parts[1]) * 1024),
                        'used' => intval($parts[2]) * 1024,
                        'used_formatted' => format_bytes(intval($parts[2]) * 1024),
                        'free' => intval($parts[3]) * 1024,
                        'free_formatted' => format_bytes(intval($parts[3]) * 1024),
                        'used_percent' => intval(rtrim($parts[4], '%'))
                    );
                }
            }
        }
        
        // Get I/O statistics
        $iostat = @shell_exec("command -v iostat >/dev/null 2>&1 && iostat -dx 1 2 | grep -v '^$' | tail -n +4");
        if ($iostat) {
            $lines = explode("\n", trim($iostat));
            $disk_info['io_stats'] = array();
            
            foreach ($lines as $line) {
                $parts = preg_split('/\s+/', trim($line));
                if (count($parts) >= 14 && !empty($parts[0])) {
                    $device = $parts[0];
                    $disk_info['io_stats'][$device] = array(
                        'reads_per_sec' => isset($parts[3]) ? (float)$parts[3] : null,
                        'writes_per_sec' => isset($parts[4]) ? (float)$parts[4] : null,
                        'read_kb_per_sec' => isset($parts[5]) ? (float)$parts[5] : null,
                        'write_kb_per_sec' => isset($parts[6]) ? (float)$parts[6] : null,
                        'await' => isset($parts[9]) ? (float)$parts[9] : null, // Average wait time in ms
                        'util_percent' => isset($parts[13]) ? (float)$parts[13] : null // Utilization percentage
                    );
                }
            }
        }
    }
    
    return $disk_info;
}

// 5. Network information
function get_network_info() {
    $network_info = array();
    
    if (function_exists('shell_exec')) {
        // Try to get network interfaces and their stats
        $interfaces_data = @shell_exec("cat /proc/net/dev | grep :");
        if ($interfaces_data) {
            $lines = explode("\n", trim($interfaces_data));
            foreach ($lines as $line) {
                $parts = preg_split('/\s+/', trim($line));
                if (count($parts) >= 10) {
                    $interface = rtrim($parts[0], ':');
                    $network_info['interfaces'][$interface] = array(
                        'rx_bytes' => isset($parts[1]) ? (int)$parts[1] : 0,
                        'rx_formatted' => isset($parts[1]) ? format_bytes((int)$parts[1]) : '0 B',
                        'tx_bytes' => isset($parts[9]) ? (int)$parts[9] : 0,
                        'tx_formatted' => isset($parts[9]) ? format_bytes((int)$parts[9]) : '0 B',
                        'rx_packets' => isset($parts[2]) ? (int)$parts[2] : 0,
                        'tx_packets' => isset($parts[10]) ? (int)$parts[10] : 0,
                        'rx_errors' => isset($parts[3]) ? (int)$parts[3] : 0,
                        'tx_errors' => isset($parts[11]) ? (int)$parts[11] : 0
                    );
                }
            }
        }
        
        // Get active connections count
        $connections = @shell_exec("netstat -an | grep ESTABLISHED | wc -l");
        if ($connections !== null) {
            $network_info['active_connections'] = (int)trim($connections);
        }
        
        // Get listening ports
        $listening_ports = @shell_exec("netstat -tuln | grep LISTEN | awk '{print $4}' | cut -d: -f2 | sort -n | uniq");
        if ($listening_ports) {
            $network_info['listening_ports'] = array_filter(explode("\n", trim($listening_ports)));
        }
        
        // Get IP addresses
        $ip_addresses = @shell_exec("hostname -I 2>/dev/null");
        if ($ip_addresses) {
            $network_info['ip_addresses'] = array_filter(explode(" ", trim($ip_addresses)));
        }
        
        // Get network traffic statistics
        $ss_stats = @shell_exec("command -v ss >/dev/null 2>&1 && ss -s");
        if ($ss_stats) {
            if (preg_match('/TCP:\s+(\d+)\s+\(estab\s+(\d+),\s+closed\s+(\d+),\s+orphaned\s+(\d+),\s+synrecv\s+(\d+),\s+timewait\s+(\d+)/', $ss_stats, $matches)) {
                $network_info['tcp_stats'] = array(
                    'total' => (int)$matches[1],
                    'established' => (int)$matches[2],
                    'closed' => (int)$matches[3],
                    'orphaned' => (int)$matches[4],
                    'synrecv' => (int)$matches[5],
                    'timewait' => (int)$matches[6]
                );
            }
        }
    }
    
    return $network_info;
}

// 6. Apache specific information
function get_apache_info() {
    $apache_info = array();
    
    // Apache version
    if (function_exists('apache_get_version')) {
        $apache_info['version'] = apache_get_version();
    } else if (function_exists('shell_exec')) {
        $version = @shell_exec('apache2 -v 2>&1 | grep "Server version"');
        if (!$version) {
            $version = @shell_exec('httpd -v 2>&1 | grep "Server version"');
        }
        if ($version) {
            $apache_info['version'] = trim($version);
        }
    }
    
    // Apache modules
    if (function_exists('apache_get_modules')) {
        $apache_info['modules'] = apache_get_modules();
        $apache_info['module_count'] = count($apache_info['modules']);
    }
    
    // Server software
    if (isset($_SERVER['SERVER_SOFTWARE'])) {
        $apache_info['server_software'] = $_SERVER['SERVER_SOFTWARE'];
    }
    
    // MPM info
    if (function_exists('shell_exec')) {
        $mpm = @shell_exec('apache2ctl -V | grep "Server MPM"');
        if ($mpm) {
            $apache_info['mpm'] = trim(str_replace('Server MPM:', '', $mpm));
        }
    }
    
    // Apache status information
    if (function_exists('shell_exec')) {
        $server_status = @shell_exec("curl -s http://localhost/server-status?auto 2>/dev/null");
        if ($server_status) {
            $lines = explode("\n", trim($server_status));
            $status_info = array();
            
            foreach ($lines as $line) {
                if (strpos($line, ':') !== false) {
                    list($key, $value) = explode(':', $line, 2);
                    $status_info[trim($key)] = trim($value);
                }
            }
            
            if (!empty($status_info)) {
                $apache_info['status'] = $status_info;
            }
        }
        
        // Get vhosts information
        $vhosts = @shell_exec("apache2ctl -S 2>/dev/null || httpd -S 2>/dev/null");
        if ($vhosts) {
            $apache_info['vhosts_info'] = trim($vhosts);
            
            // Parse number of vhosts
            if (preg_match_all('/namevhost\s+/i', $vhosts, $matches)) {
                $apache_info['vhosts_count'] = count($matches[0]);
            }
        }
    }
    
    return $apache_info;
}

// 7. PHP information
function get_php_info() {
    $php_info = array(
        'version' => PHP_VERSION,
        'sapi' => php_sapi_name(),
        'os' => PHP_OS,
        'max_execution_time' => ini_get('max_execution_time'),
        'memory_limit' => ini_get('memory_limit'),
        'upload_max_filesize' => ini_get('upload_max_filesize'),
        'post_max_size' => ini_get('post_max_size'),
        'display_errors' => ini_get('display_errors'),
        'extension_count' => count(get_loaded_extensions()),
        'extensions' => get_loaded_extensions(),
        'zend_version' => zend_version(),
        'opcache_enabled' => function_exists('opcache_get_status') ? (bool)opcache_get_status(false)['opcache_enabled'] : false,
        'default_timezone' => date_default_timezone_get(),
        'disabled_functions' => ini_get('disable_functions'),
        'thread_safety' => defined('PHP_ZTS') && PHP_ZTS ? 'enabled' : 'disabled',
        'error_reporting_level' => error_reporting(),
        'open_basedir' => ini_get('open_basedir'),
        'date_time' => date('Y-m-d H:i:s')
    );
    
    // Get PHP-FPM status if available
    if (function_exists('shell_exec')) {
        $fpm_status = @shell_exec("curl -s 'http://localhost/status?json' 2>/dev/null");
        if ($fpm_status && $fpm_json = json_decode($fpm_status, true)) {
            $php_info['fpm_status'] = $fpm_json;
        }
    }
    
    return $php_info;
}

// 8. Process information
function get_process_info() {
    $process_info = array();
    
    if (function_exists('shell_exec')) {
        // Get current process info
        $pid = getmypid();
        if ($pid) {
            $process_info['pid'] = $pid;
            
            // Try to get process details
            $process_details = @shell_exec("ps -p $pid -o %cpu,%mem,rss,vsz 2>/dev/null");
            if ($process_details) {
                $lines = explode("\n", trim($process_details));
                if (count($lines) > 1) {
                    $headers = preg_split('/\s+/', trim($lines[0]));
                    $values = preg_split('/\s+/', trim($lines[1]));
                    
                    for ($i = 0; $i < count($headers) && $i < count($values); $i++) {
                        $header = strtolower(trim($headers[$i]));
                        $value = trim($values[$i]);
                        
                        if ($header === 'rss' || $header === 'vsz') {
                            // Convert KB to bytes
                            $value_bytes = intval($value) * 1024;
                            $process_info[$header] = $value_bytes;
                            $process_info[$header . '_formatted'] = format_bytes($value_bytes);
                        } else {
                            $process_info[$header] = $value;
                        }
                    }
                }
            }
        }
        
        // Get top processes by CPU and memory
        $top_processes = @shell_exec("ps aux --sort=-%cpu,-%mem | head -6");
        if ($top_processes) {
            $lines = explode("\n", trim($top_processes));
            $headers = preg_split('/\s+/', trim($lines[0]), 11);
            
            $top_process_list = array();
            for ($i = 1; $i < count($lines); $i++) {
                $process = array();
                $parts = preg_split('/\s+/', trim($lines[$i]), 11);
                
                for ($j = 0; $j < count($headers) && $j < count($parts); $j++) {
                    $process[strtolower($headers[$j])] = $parts[$j];
                }
                
                if (!empty($process)) {
                    $top_process_list[] = $process;
                }
            }
            
            if (!empty($top_process_list)) {
                $process_info['top_processes'] = $top_process_list;
            }
        }
        
        // Get total number of processes
        $total_processes = @shell_exec("ps ax | wc -l");
        if ($total_processes !== null) {
            $process_info['total_count'] = (int)trim($total_processes);
        }
        
        // Get process summary by state
        $process_states = @shell_exec("ps ax -o stat | grep -v STAT | cut -c1 | sort | uniq -c");
        if ($process_states) {
            $state_lines = explode("\n", trim($process_states));
            $states = array();
            
            foreach ($state_lines as $line) {
                $parts = preg_split('/\s+/', trim($line));
                if (count($parts) == 2) {
                    $count = (int)$parts[0];
                    $state = $parts[1];
                    
                    $state_name = '';
                    switch ($state) {
                        case 'R': $state_name = 'running'; break;
                        case 'S': $state_name = 'sleeping'; break;
                        case 'D': $state_name = 'disk_sleep'; break;
                        case 'Z': $state_name = 'zombie'; break;
                        case 'T': $state_name = 'stopped'; break;
                        case 't': $state_name = 'tracing_stop'; break;
                        case 'X': $state_name = 'dead'; break;
                        default: $state_name = 'other';
                    }
                    
                    $states[$state_name] = $count;
                }
            }
            
            if (!empty($states)) {
                $process_info['states'] = $states;
            }
        }
    }
    
    return $process_info;
}

// 9. System users information (NEW)
function get_users_info() {
    $users_info = array();
    
    if (function_exists('shell_exec')) {
        // Get logged-in users
        $logged_in_users = @shell_exec("who");
        if ($logged_in_users) {
            $lines = explode("\n", trim($logged_in_users));
            $users = array();
            
            foreach ($lines as $line) {
                $parts = preg_split('/\s+/', trim($line));
                if (count($parts) >= 5) {
                    $user_entry = array(
                        'username' => $parts[0],
                        'tty' => $parts[1],
                        'login_time' => $parts[2] . ' ' . $parts[3],
                        'from' => isset($parts[4]) && strpos($parts[4], '(') !== false ? 
                            trim($parts[4], '()') : 'local'
                    );
                    
                    $users[] = $user_entry;
                }
            }
            
            $users_info['logged_in'] = array(
                'count' => count($users),
                'users' => $users
            );
        }
        
        // Get last logins
        $last_logins = @shell_exec("last -n 10");
        if ($last_logins) {
            $lines = explode("\n", trim($last_logins));
            $last_login_list = array();
            
            foreach ($lines as $line) {
                if (strpos($line, 'wtmp begins') === false && !empty(trim($line))) {
                    $parts = preg_split('/\s+/', trim($line));
                    if (count($parts) >= 5) {
                        $last_login_entry = array(
                            'username' => $parts[0],
                            'tty' => $parts[1],
                            'from' => $parts[2] === ':0' ? 'local' : $parts[2],
                            'login_time' => implode(' ', array_slice($parts, 3, 3))
                        );
                        
                        $last_login_list[] = $last_login_entry;
                    }
                }
            }
            
            if (!empty($last_login_list)) {
                $users_info['last_logins'] = $last_login_list;
            }
        }
        
        // Get system users count
        $system_users_count = @shell_exec("cat /etc/passwd | wc -l");
        if ($system_users_count !== null) {
            $users_info['system_users_count'] = (int)trim($system_users_count);
        }
        
        // Get user with highest CPU usage
        $high_cpu_user = @shell_exec("ps aux --sort=-%cpu | head -2 | tail -1 | awk '{print $1}'");
        if ($high_cpu_user) {
            $users_info['highest_cpu_user'] = trim($high_cpu_user);
        }
    }
    
    return $users_info;
}

// 10. Security information (NEW)
function get_security_info() {
    $security_info = array();
    
    if (function_exists('shell_exec')) {
        // Get failed login attempts
        $failed_logins = @shell_exec("grep 'Failed password' /var/log/auth.log 2>/dev/null | wc -l");
        if ($failed_logins !== null && is_numeric(trim($failed_logins))) {
            $security_info['failed_login_attempts'] = (int)trim($failed_logins);
        }
        
        // Check if firewall is enabled
        $firewall_status = @shell_exec("command -v ufw >/dev/null 2>&1 && ufw status | grep -i 'Status: active' | wc -l");
        if ($firewall_status !== null) {
            $security_info['firewall_enabled'] = ((int)trim($firewall_status)) > 0;
        }
        
        // Check SELinux status
        $selinux_status = @shell_exec("command -v getenforce >/dev/null 2>&1 && getenforce");
        if ($selinux_status) {
            $security_info['selinux_status'] = trim($selinux_status);
        }
        
        // Get listening services
        $listening_services = @shell_exec("netstat -tulpn 2>/dev/null | grep LISTEN");
        if ($listening_services) {
            $lines = explode("\n", trim($listening_services));
            $services = array();
            
            foreach ($lines as $line) {
                $parts = preg_split('/\s+/', trim($line));
                if (count($parts) >= 7) {
                    $port_data = explode(':', $parts[3]);
                    $port = end($port_data);
                    $program = isset($parts[6]) ? preg_replace('/^\d+\//', '', $parts[6]) : 'unknown';
                    
                    $services[] = array(
                        'proto' => $parts[0],
                        'port' => $port,
                        'program' => $program
                    );
                }
            }
            
            if (!empty($services)) {
                $security_info['listening_services'] = $services;
            }
        }
        
        // Check for updates
        $updates_available = @shell_exec("command -v apt >/dev/null 2>&1 && apt list --upgradable 2>/dev/null | grep -v 'Listing' | wc -l");
        if ($updates_available !== null && is_numeric(trim($updates_available))) {
            $security_info['updates_available'] = (int)trim($updates_available);
        }
    }
    
    return $security_info;
}

// 11. Kernel information (NEW)
function get_kernel_info() {
    $kernel_info = array();
    
    if (function_exists('shell_exec')) {
        // Kernel version
        $kernel_version = @shell_exec("uname -r");
        if ($kernel_version) {
            $kernel_info['version'] = trim($kernel_version);
        }
        
        // Kernel name
        $kernel_name = @shell_exec("uname -s");
        if ($kernel_name) {
            $kernel_info['name'] = trim($kernel_name);
        }
        
        // Kernel architecture
        $kernel_arch = @shell_exec("uname -m");
        if ($kernel_arch) {
            $kernel_info['architecture'] = trim($kernel_arch);
        }
        
        // Kernel parameters
        $kernel_params = @shell_exec("sysctl -a 2>/dev/null | grep -E '^kernel.(hostname|domainname|ostype|osrelease|version)' | sort");
        if ($kernel_params) {
            $lines = explode("\n", trim($kernel_params));
            $params = array();
            
            foreach ($lines as $line) {
                if (strpos($line, '=') !== false) {
                    list($key, $value) = explode('=', $line, 2);
                    $key = str_replace('kernel.', '', trim($key));
                    $params[$key] = trim($value);
                }
            }
            
            if (!empty($params)) {
                $kernel_info['parameters'] = $params;
            }
        }
        
        // Kernel modules count
        $module_count = @shell_exec("lsmod | wc -l");
        if ($module_count !== null && is_numeric(trim($module_count))) {
            // Subtract 1 for the header line
            $kernel_info['loaded_modules_count'] = max(0, (int)trim($module_count) - 1);
        }
    }
    
    return $kernel_info;
}

// Execute all measurements
try {
    $result['equation_solving_time_ms'] = measure_equation_solving_time(10000);
    $result['memory'] = get_memory_info();
    $result['cpu'] = get_cpu_info();
    $result['disk'] = get_disk_info();
    $result['network'] = get_network_info();
    $result['apache'] = get_apache_info();
    $result['php'] = get_php_info();
    $result['process'] = get_process_info();
    
    // New statistics
    $result['users'] = get_users_info();
    $result['security'] = get_security_info();
    $result['kernel'] = get_kernel_info();

    // Add server info with improved uptime display
    $raw_uptime = function_exists('shell_exec') ? @trim(shell_exec('uptime')) : null;
    $formatted_uptime = format_uptime($raw_uptime);
    
    $result['server'] = array(
        'hostname' => @gethostname(),
        'ip' => isset($_SERVER['SERVER_ADDR']) ? $_SERVER['SERVER_ADDR'] : null,
        'time' => date('Y-m-d H:i:s'),
        'timezone' => date_default_timezone_get(),
        'uptime_raw' => $raw_uptime,
        'uptime' => $formatted_uptime
    );
    
    // Add system load indicators
    if (function_exists('sys_getloadavg')) {
        $load = sys_getloadavg();
        $result['server']['load_averages'] = array(
            '1min' => $load[0],
            '5min' => $load[1],
            '15min' => $load[2]
        );
    }
} catch (Exception $e) {
    $result['error'] = array(
        'message' => $e->getMessage(),
        'code' => $e->getCode(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    );
}

// Calculate total script execution time
$script_end_time = microtime(true);
$result['script_execution_time_ms'] = round(($script_end_time - $script_start_time) * 1000, 2);

// Output the result as JSON
echo json_encode($result, JSON_PRETTY_PRINT);
?>