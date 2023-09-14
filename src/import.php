<?php
##########################################################
# mysql import
# 2017/06/29 by TsengYuchen
##########################################################
// error_reporting(0);

$host = $argv[1] ?? '';
$dbname = '{DBNAME}';
$trg_user = "";
$trg_pass = "";

foreach ($argv as $k=>$v)
{
    if ($k<2)
    {
        continue;
    }
    if (substr($v, 0, 1)!='-')
    {
        continue;
    }

    switch (substr($v, 0, 2))
    {
        case '-u':
            $trg_user = substr($v, 2);
            break;
        case '-p':
            $trg_pass = substr($v, 2);
            break;
    }
}

if (!$host)
{
    echo "host is empty !\n";
    exit;
}
$_host = explode(':', $host);
if (count($_host)==1)
{
    $_host[1] = '3306';
}

$cnf = explode("\n", file_get_contents('./_import.cnf'));
foreach ($cnf as $k=>$v)
{
    $v = str_replace(' ', '', $v);
    if (stristr('^'.$v, 'user=') && $trg_user)
    {
        $cnf[$k] = 'user = '.$trg_user;
        continue;
    }
    if (stristr('^'.$v, 'password=') && $trg_pass)
    {
        $cnf[$k] = 'password = '.$trg_pass;
        continue;
    }
}
$tempcnf = '~'.date('Ymd').'_'.uniqid().'.cnf';
file_put_contents($tempcnf, implode("\n", $cnf));

echo "\n\n\n";
echo "################################################################################\n";
echo "Import to ".$host." ".$dbname."\n";
echo date('Y-m-d H:i:s')."\n";
echo "################################################################################";

$cnf = "_import.cnf";
$f = scandir('.');
$k=0;
foreach ($f as $ff)
{
    if (strtolower(substr($ff, -4))!='.sql')
    {
        continue;
    }

    $output = null;
    $return_var = null;
    $cmd = "mysql --defaults-extra-file=".$tempcnf." --host=".$_host[0]." --port=".$_host[1]." ".$dbname." < ".$ff." 2>&1";
    $pad = 76 - strlen($ff);

    echo "\n".sprintf("%03d ", $k+1);
    echo $ff;
    for ($i=0;$i<$pad;$i++)
    {
        echo '.';
    }

    exec($cmd, $output, $return_var);

    if ($return_var===0)
    {
        echo "Done";
    }
    else if ($return_var===1)
    {
        echo $output[0];
    }

    $k++;
}

if (file_exists($tempcnf))
{
    unlink($tempcnf);
}

echo "\n";
echo "Import finish.\n";
echo "\n";
