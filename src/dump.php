<?php
##########################################################
# mysql dump
# 2017/06/29 create by TsengYuchen
#
# 2017/08/23 modify by TsengYuchen
# 2018/08/09 modify by TsengYuchen
#
##########################################################
// error_reporting(0);

$host = $argv[1] ?? '';
$dbname = $argv[2] ?? '';
$save_route = "";
$src_user = "";
$src_pass = "";
$trg_user = "";
$trg_pass = "";
foreach ($argv as $k=>$v)
{
    if ($k<3)
    {
        continue;
    }
    if (substr($v, 0, 1)!='-')
    {
        continue;
    }
    switch (substr($v, 0, 3))
    {
        case '-su':
            $src_user = substr($v, 3);
            break;
        case '-sp':
            $src_pass = substr($v, 3);
            break;
        case '-tu':
            $trg_user = substr($v, 3);
            break;
        case '-tp':
            $trg_pass = substr($v, 3);
            break;
        default:
            switch (substr($v, 0, 2))
            {
                case '-u':
                    $src_user = substr($v, 2);
                    $trg_user = $src_user;
                    break;
                case '-p':
                    $src_pass = substr($v, 2);
                    $trg_pass = $src_pass;
                    break;
                case '-r':
                    $save_route = str_replace(array('\'', '"'), '', substr($v, 2));
                    break;
            }
            break;
    }
}

if (!$save_route)
{
    $save_route = str_replace(basename(__file__), '', __file__);
}
if (!$src_user) { $src_user = 'mysql'; }
if (!$src_pass) { $src_pass = 'password'; }
if (!$trg_user) { $trg_user = 'mysql'; }
if (!$trg_pass) { $trg_pass = 'password'; }

if (!$host)
{
    echo "host is empty !\n";
    exit;
}
if (!$dbname)
{
    echo "db name is empty !\n";
    exit;
}

$tbl = '';
if (strstr($dbname, '.'))
{
    $tmp = explode('.', $dbname);
    $dbname = $tmp[0];
    $tbl = $tmp[1];
}

$no_data = false;
if (substr($dbname, 0, 1)=='^') //開頭^表只匯出結構
{
    $no_data = true;
    $dbname = substr($dbname, 1);
}

$_host = explode(':', $host);
if (count($_host)==1)
{
    $_host[1] = '3306';
}

$dsn = "mysql:host=".$_host[0].";dbname=".$dbname.";port=".$_host[1];
$db = new PDO($dsn, $src_user, $src_pass);

$db->exec('SET NAMES UTF8');

// $sql = "SHOW TABLES";
$sql = "SHOW TABLE STATUS ";
$tbl_filter = 0;
if ($tbl)
{
    $sql .= " WHERE Name like '".str_replace('*', '%', $tbl)."'";
    $tbl_filter = 1;
}
$q = $db->query($sql);

if ($q->rowCount() == 0)
{
    echo 'No table found.'."\n";
    exit;
}

$cnf0 = file_get_contents('./_dump.cnf');
$cnf = sprintf($cnf0, $src_user, $src_pass);
$tempcnf = '~'.date('Ymd').'_'.uniqid().'.cnf';
file_put_contents($tempcnf, $cnf);

$tempcnf = realpath($tempcnf);

echo "\n\n\n";
echo "################################################################################\n";
echo "Dump from ".$host." ".$dbname."\n";
echo "################################################################################";

$tbl_num = 0;
if ($q->rowCount() > 0)
{
    $fdr = $dbname.'_dump_'.date('YmdHis');
    $dir_path = $save_route.$fdr;

    $clearDay = 0.4;
    // N天前刪除
    foreach (scandir($save_route) as $v)
    {
        if (substr($v,0,1)=='.' || substr($v,0,4)=='lost' || substr($v,0,3)=='bk_')
        {
            continue;
        }
        if (is_dir($save_route.$v))
        {
            $dd = substr($v, -14);
            @$tt = mktime(substr($dd,8,2), substr($dd,10,2), substr($dd,12,2), substr($dd,4,2), substr($dd,6,2), substr($dd,0,4));
            $diff_day = (time() - $tt)/86400;
            if ($diff_day > $clearDay)
            {
                //用 system call
                system("rm -rf ".escapeshellarg($save_route.$v));
            }
        }
    }

    mkdir($dir_path);
    system("mv ".$tempcnf." ".$dir_path.'/'.basename($tempcnf));
    $tempcnf = $dir_path.'/'.basename($tempcnf);

    //schema
    if (isset($_ndbcluster) && true===$_ndbcluster)
    {
        $opt = "";
        if (!$tbl_filter) //有tbl不匯出全部
        {
            $f0 = '000_'.$dbname.'_schema';
            $cmd = "mysqldump --defaults-extra-file=".$tempcnf." --host=".$_host[0]." --port=".$_host[1]." --default-character-set=utf8 --no-data ".$opt." --databases ".$dbname." > ".$dir_path."/".$f0.'.sql'." 2>&1";
            $pad = 76 - strlen($f0) + 6;
            echo "\n".sprintf("%03d ", 0);
            echo $f0.'.sql';
            for ($i=0;$i<$pad;$i++)
            {
                echo '.';
            }
            exec($cmd, $output, $return_var);
            if ($return_var===0)
            {
                echo "Done";
            }
            else
            {
                echo "Fail";
            }
        }

        $ns = '';
        $f0 = $dir_path."/".$f0.'.sql';
        if (file_exists($f0))
        {
            $fp = fopen($f0, "r");
            $tbl_now = '';
            $cc = array();
            if ($fp)
            {
                while (!feof($fp))
                {
                    $buffer = fgets($fp); //fgets為每次讀取一列文字

                    if (stristr($buffer, 'DROP TABLE IF EXISTS'))
                    {
                        $tmp = explode('EXISTS', $buffer);
                        $tbl_now = $tmp[1];
                        $tbl_now = str_replace(array('`', ';'), '', $tbl_now);
                        $tbl_now = trim($tbl_now);
                        $ns.=$buffer;
                        continue;
                    }
                    if (stristr($buffer, ' ENGINE='))
                    {
                        if (substr($ns, -2)==",\n")
                        {
                            $ns = substr($ns, 0, -2)."\n";
                        }
                        $buffer = str_ireplace('ENGINE=InnoDB', 'ENGINE=ndbcluster', $buffer);
                        $buffer = str_ireplace('ENGINE=MyISAM', 'ENGINE=ndbcluster', $buffer);
                        $buffer = str_ireplace('ENGINE=MEMORY', 'ENGINE=ndbcluster', $buffer);
                        $ns.=$buffer;
                        continue;
                    }
                    if (stristr($buffer, 'Dump completed'))
                    {
                        $ns .= implode("", $cc)."\n\n";
                        continue;
                    }
                    if (stristr($buffer, 'CONSTRAINT '))
                    {
                        $buffer = trim($buffer);
                        if (substr($buffer, -1)==',')
                        {
                            $buffer = substr($buffer, 0, -1);
                        }
                        $cc[] = ' ALTER TABLE `'.$tbl_now.'` ADD '.$buffer.";\n";
                    }
                    else
                    {
                        $ns.=$buffer;
                    }
                }
            }
            fclose($fp);
            file_put_contents($f0, $ns);
        }
    }
    else
    {
        $_ndbcluster = false;
    }

    if ($no_data)
    {
        echo "\n";
    }
    else
    {
        $max_row = 9999;
        foreach ($q as $k => $row)
        {
            $output = null;
            $return_var = 0;

            $opt = "";

            // if (in_array($row[0], array('ma_contact_file')))
            // {
            //     continue;
            // }

            // $fff = $dir_path."/".$row[0].'.sql';
            // 結構與資料分開
            foreach (array(
                $dir_path."/%s"."_A_".$row[0].'.sql',
                $dir_path."/%s"."_B_".$row[0].'.sql'
            ) as $xxx=>$fff)
            {
                $opt = "";
                if ($row['Rows'] > 100000)
                {
                    $opt = '  --opt --where=" 1 ORDER BY serial  DESC LIMIT '.($max_row*100).'" ';
                }

                $fff = sprintf($fff, sprintf("%03d", $k+1));

                if ($xxx==0)
                {
                    if ($tbl_filter || $_ndbcluster===false)
                    {
                        $cmd = "mysqldump --defaults-extra-file=".$tempcnf." --host=".$_host[0]." --port=".$_host[1]." --column-statistics=0 --default-character-set=utf8 --no-data ".$opt." --databases ".$dbname." --tables ".$row[0]." > ".$fff." 2>&1";
                    }
                    else
                    {
                        continue;
                    }
                }
                else
                {
                    $cmd = "mysqldump --defaults-extra-file=".$tempcnf." --host=".$_host[0]." --port=".$_host[1]." --column-statistics=0 --default-character-set=utf8 --no-create-info --skip-triggers ".$opt." --databases ".$dbname." --tables ".$row[0]." > ".$fff." 2>&1";
                }
                $pad = 76 - strlen($row[0]);

                echo "\n".sprintf("%03d ", $k+1);
                echo basename($fff).'';

                for ($i=0;$i<$pad;$i++)
                {
                    echo '.';
                }

                exec($cmd, $output, $return_var);

                $tbl_num ++;

                if ($return_var===0)
                {
                    echo "Done";
                }
                else if ($return_var===2)
                {
                    $fp = fopen($fff, "r");
                    while (($buffer = fgets($fp, 4096)) !== false)
                    {
                        echo $buffer;
                        break;
                    }
                    fclose($fp);
                    echo ' Retry >>>>>> ';
                    for ($z=0;$z<15;$z++)
                    {
                        sleep(1);
                        exec($cmd, $output, $return_var);
                        if ($return_var===0)
                        {
                            break;
                        }
                    }
                    if ($return_var!==0)
                    {
                        echo "Fail";
                    }
                    else
                    {
                        echo "Done";
                    }
                }
            }
        }

        if ($tbl_num)
        {
            //copy import file to dir
            echo "\n";
            $ss = file_get_contents($save_route.'import.php');
            $ss = str_replace('{DBNAME}', $dbname, $ss);
            file_put_contents($dir_path.'/import.php', $ss);
        }
        else
        {
            //用 system call
            system("rm -rf ".escapeshellarg($dir_path));
        }
    }

}

if (file_exists($tempcnf))
{
    system("mv ".$tempcnf." ".$dir_path.'/_import.cnf');

    $cnf0 = file_get_contents('./_dump.cnf');
    $cnf = sprintf($cnf0, $trg_user, $trg_pass);
    $cnf = str_replace('[mysqldump]', '[mysql]', $cnf);
    file_put_contents($dir_path.'/_import.cnf', $cnf);
}

if (file_exists($tempcnf))
{
    unlink($tempcnf);
}

if ($tbl_num or $no_data)
{
    echo "Dump finish. 請進入資料夾 (".$fdr."), 執行 php import.php {DB} 匯入\n";
}
else
{
    echo "\n無可匯入的資料表.\n";
}

echo "\n";
