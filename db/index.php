<?php
// echo '';
// exit();

// ob_end_flush();
// ob_implicit_flush();

// ob_start();
// for ($i = 3; $i > 0; $i--)
// {
	// echo $i.'<br />';
	// ob_flush();
	// flush();
	// sleep(1);
// }

ob_start(); 
ob_end_flush();
ob_implicit_flush(1);

for ($i=0; $i<10; $i++)
{
	echo str_repeat(" ",4096); //确保足够的字符，立即输出，Linux服务器可以去掉这个语句
	echo $i."<br>";
	sleep(1);
}

// require '';