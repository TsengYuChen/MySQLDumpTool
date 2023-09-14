<?php
##########################################################
# mysql import from
# 2020/05/05 by TsengYuchen
#
# 從正式備份區匯入
# php svr_import.php -to192.168.X.XXX -dbmy_db -qSC_*
##########################################################
// error_reporting(0);

// //getopt param1: string, para2: array
// $options = getopt("to:db::u::p::k:q::"); //:必填, ::非必填
// 自訂
$options = array();
foreach ($argv as $k=>$v)
{
    if (substr($v, 0, 1)!='-')
    {
        continue;
    }
    switch (substr($v, 0, 3))
    {
        case '-to':
            $options['to'] = substr($v, 3);
            break;
        case '-db':
            $options['db'] = substr($v, 3);
            break;
        default:
            switch (substr($v, 0, 2))
            {
                case '-u':
                    $options['u'] = substr($v, 2);
                    break;
                case '-p':
                    $options['p'] = substr($v, 2);
                    break;
                case '-q':
                    $options['q'] = substr($v, 2);
                    break;
            }
            break;
    }
}

$host   = $options['to'] ?? '';
$dbname = $options['db'] ?? '';
$user   = $options['u'] ?? 'mysql';
$pwd    = $options['p'] ?? 'password';
$filter = $options['q'] ?? '';
$filter = trim($filter);

if (!$host)
{
    echo "host is empty !\n";
    exit;
}
if (!$dbname)
{
    echo "dbname is empty !\n";
    exit;
}
$_host = explode(':', $host);
if (count($_host)==1)
{
    $_host[1] = '3306';
}

$cnf = array(
    '[mysql]',
    'user='.$user,
    'password='.$pwd
);
$tempcnf = '~'.date('Ymd').'_'.uniqid().'.cnf';
file_put_contents($tempcnf, implode("\n", $cnf));

$db_bk_path = $options['k']  ?? date('Ymd'); //備份日期
$db_bk_path .= '/ErisDB/';
$db_bk_path = '/media/sf_DBData/'.$db_bk_path;

//找最新目錄
$dir = '';
foreach (scandir($db_bk_path) as $v)
{
    if (stristr('^'.$v, $dbname.'_dump_'))
    {
        $dir = $v;
    }
}
$target_path = $db_bk_path.$dir.'/';

echo "\n\n\n";
echo "################################################################################\n";
echo "Import to ".$host." ".$dbname;
echo "\n";
echo "From: ".$target_path;
echo "\n";
echo date('Y-m-d H:i:s')."\n";
echo "################################################################################";


$f = scandir($target_path);
$k=0;
foreach ($f as $ff)
{
    if (strtolower(substr($ff, -4))!='.sql')
    {
        continue;
    }

    if ($filter)
    {
        $ff2 = str_replace('.sql', '', $ff);

        $skip = array();
        $fs = explode('*', $filter);
        //開頭
        if ($fs[0] == '')
        {
        }
        else
        {
            if (substr($ff2, 0, strlen($fs[0]))!=$fs[0])
            {
                continue;
            }
        }
        //末端
        $ek = 0;
        if (COUNT($fs)>1)
        {
            $ek = COUNT($fs) - 1;
            if ($fs[$ek] == '')
            {
            }
            else
            {
                $ff2 = str_replace('.sql', '', $ff);
                if (substr($ff2, -1*strlen($fs[$ek]))!=$fs[$ek])
                {
                    continue;
                }
            }
        }
        //中間
        $ff3 = $ff2;
        $pass = true;
        foreach ($fs as $fsk=>$fsv)
        {
            if ($fsk && $fsk!=$ek)
            {
                if (false===strstr('^'.$ff3, $fsv))
                {
                    $pass = false;
                }
                $stmp = explode($fsv, $ff3);
                array_shift($stmp);
                $ff3 = implode('', $stmp);
            }
        }
        if (false===$pass)
        {
            continue;
        }
    }

    $output = null;
    $return_var = null;
    $cmd = "mysql --defaults-extra-file=".$tempcnf." --host=".$_host[0]." --port=".$_host[1]." ".$dbname." < ".$target_path.$ff." 2>&1";

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
