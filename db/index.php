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
	echo str_repeat(" ",4096); //ȷ���㹻���ַ������������Linux����������ȥ��������
	echo $i."<br>";
	sleep(1);
}

// require '';