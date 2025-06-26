<?php
if (extension_loaded('gd')) {
    echo "GD is installed and enabled!";
    echo "\nGD Version: " . gd_info()['GD Version'];
} else {
    echo "GD is NOT installed or enabled!";
}
?> 