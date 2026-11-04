<?php
require 'config.php';

session_end();
header('Location: index.php?logged_out=1');
exit;
