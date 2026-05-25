<?php
// Permanent redirect z původního index.php na nový blog.php
header("HTTP/1.1 301 Moved Permanently");
header("Location: blog.php");
exit;