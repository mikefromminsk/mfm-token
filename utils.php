<?php
include_once $_SERVER["DOCUMENT_ROOT"] . "/mfm-db/utils.php";
include_once $_SERVER["DOCUMENT_ROOT"] . "/mfm-analytics/utils.php";

$gas_domain = get_config_required(gas_domain);

function tokenKey($domain, $address, $password, $prev_key = "")
{
    return md5($domain . $address . $password . $prev_key);
}

function tokenNextHash($domain, $address, $password, $prev_key = "")
{
    return md5(tokenKey($domain, $address, $password, $prev_key));
}

function tokenPass($domain, $address, $password)
{
    $account = getAccount($domain, $address);
    $key = tokenKey($domain, $address, $password, $account[prev_key]);
    $next_hash = tokenNextHash($domain, $address, $password, $key);
    return "$key:$next_hash";
}

function tokenTrans($domain, $address, $page, $size)
{
    $sql = "select * from trans where 1 = 1";
    if ($address != null)
        $sql .= " and (`from` = '$address' or `to` = '$address')";
    if ($domain != null)
        $sql .= " and `domain` = '$domain'";
    $sql .= " order by time desc limit " . ($page - 1) * $size . ", $size";
    return select($sql);
}

function tokenFirstTran($domain)
{
    return selectRow("select * from `trans` where `domain` = '$domain' and `from` = 'owner' order by `id` limit 1");
}

function tokenTran($next_hash)
{
    return selectRow("select * from `trans` where `next_hash` = '$next_hash'");
}

function tokenLastTran($domain, $address)
{
    return selectRow("select * from `trans` where `domain` = '$domain' and `from` = '$address' order by `id` desc limit 1");
}

function tokenOwner($domain)
{
    return tokenFirstTran($domain)[to];
}

function tokenBalance($domain, $address)
{
    $address = getAccount($domain, $address);
    if ($address != null) {
        return $address[balance];
    }
    return null;
}

function tokenRegAccount($domain, $address, $password, $amount = 0)
{
    // block if $amount > 0 and domain exists
    if (getAccount($domain, $address) == null) {
        return requestEquals("/mfm-token/send.php", [
            domain => $domain,
            from_address => owner,
            to_address => $address,
            amount => "$amount",
            pass => ":" . tokenNextHash($domain, $address, $password),
        ]);
    } else {
        return false;
    }
}

function tokenRegScript($domain, $address, $script)
{
    if (getAccount($domain, $address) == null) {
        return requestEquals("/mfm-token/send.php", [
            domain => $domain,
            from_address => owner,
            to_address => $address,
            amount => "0", // TODO если отправить 0 то ошибка
            pass => ":",
            delegate => $script,
        ]);
    } else {
        return false;
    }
}

function requestAccount($domain, $address)
{
    return http_post("/mfm-token/account.php", [
        domain => $domain,
        address => $address,
    ]);
}

function tokenSendAndCommit($domain, $from, $to, $password, $amount)
{
    $account = requestAccount($domain, $from);
    if ($account != null) {
        $key = tokenKey($domain, $from, $password, $account[prev_key]);
        $next_hash = tokenNextHash($domain, $from, $password, $key);
        return requestEquals("/mfm-token/send.php", [
            domain => $domain,
            from_address => $from,
            to_address => $to,
            amount => "$amount",
            pass => "$key:$next_hash",
        ]);
    } else {
        return false;
    }
}

function commitAccounts()
{
    if ($GLOBALS[accounts] != null) {
        foreach ($GLOBALS[accounts] as $domain => $accounts) {
            foreach ($accounts as $address => $account) {
                $commit = $account[commit];
                unset($account[commit]);
                if ($commit == insert) {
                    insertRow(accounts, $account);
                    trackAccumulate($domain . _accounts);
                } else if ($commit == update) {
                    updateWhere(accounts, $account, [domain => $domain, address => $address]);
                }
            }
        }
    }
}

function setAccount($domain, $address, $params)
{
    $account = getAccount($domain, $address);
    if ($account == null) {
        $account = $params;
        $account[commit] = insert;
        $account[domain] = $domain;
        $account[address] = $address;
    } else {
        if ($account[commit] == null) {
            $account[commit] = update;
        }
        foreach ($params as $param => $value) {
            $account[$param] = $value;
        }
    }
    $GLOBALS[accounts][$domain][$address] = $account;
}

// todo change Address to Account and in schema
function getAccount($domain, $address)
{
    if ($GLOBALS[accounts] == null) {
        $GLOBALS[accounts] = [];
    }
    if ($GLOBALS[accounts][$domain] == null) {
        $GLOBALS[accounts][$domain] = [];
    }
    $account = $GLOBALS[accounts][$domain][$address];
    if ($account == null) {
        $account = selectRowWhere(accounts, [domain => $domain, address => $address]);
    }
    $GLOBALS[accounts][$domain][$address] = $account;
    return $account;
}

function saveTran($tran)
{
    if ($GLOBALS[trans] == null) {
        $GLOBALS[trans] = [];
    }
    $GLOBALS[trans][] = $tran;
}

function commitTrans()
{
    if ($GLOBALS[trans] != null) {
        $trans_in_insert_sequence = array_reverse($GLOBALS[trans]);
        foreach ($trans_in_insert_sequence as $tran) {
            insertRow(trans, $tran);
            broadcast(transactions, $tran);
            trackAccumulate($tran[domain] . _trans);
        }
    }
}

function tokenSend(
    $domain,
    $from_address,
    $to_address,
    $amount,
    $pass = ":",
    $delegate = null
)
{
    if ($pass != null) {
        $key = explode(":", $pass)[0];
        $next_hash = explode(":", $pass)[1];
    }
    if ($amount !== round($amount, 2)) error("amount tick is 0.01");
    if ($amount < 0) error("amount less than 0");

    if ($from_address == owner) {
        if (strlen($domain) < 3 || strlen($domain) > 32) error("domain length has to be between 3 and 32");
        if (tokenBalance($domain, owner) === null) {
            setAccount($domain, owner, [
                prev_key => "",
                next_hash => "",
                balance => $amount,
                delegate => "mfm-token/send.php",
            ]);
        }
        if (tokenBalance($domain, $to_address) === null) {
            setAccount($domain, $to_address, [
                prev_key => "",
                next_hash => $next_hash,
                balance => 0,
                delegate => $delegate,
            ]);
        }
    }

    $from = getAccount($domain, $from_address);
    $to = getAccount($domain, $to_address);
    $from[balance] = round($from[balance], 2);
    if ($from[balance] < $amount) error(strtoupper($domain) . " balance is not enough in $from_address wallet. Balance: $from[balance] Need: $amount");
    if ($to == null) error("$to_address receiver doesn't exist");
    if ($from[delegate] != null) {
        if ($from[delegate] != scriptPath())
            error("script " . scriptPath() . " cannot use $from_address address. Only " . $from[delegate]);
    } else {
        if ($from[next_hash] != md5($key)) error("$domain key is not right");
    }

    if ($from[delegate] != null) {
        setAccount($domain, $from_address, [
            balance => round($from[balance] - $amount, 2),
        ]);
    } else {
        setAccount($domain, $from_address, [
            balance => round($from[balance] - $amount, 2),
            prev_key => $key,
            next_hash => $next_hash,
        ]);
    }

    $first_tran = tokenFirstTran($domain);
    $owner_address = $first_tran[to];
    $owner = getAccount($domain, $owner_address);
    $fee = 0;
/*    if ($owner != null
        && $from_address != $owner_address
        && strpos($from_address, exchange_) !== 0 // can be removed
        && strpos($to_address, exchange_) !== 0) {
        $fee_percent = round($owner[balance]  / $first_tran[amount] * 100, 2);
        $fee = round($amount / (1 + $fee_percent) * $fee_percent, 2);
        setAccount($domain, $owner_address, [
            balance => round($owner[balance] + $fee, 2)
        ]);
    }*/

    setAccount($domain, $to_address, [
        balance => round($to[balance] + $amount - $fee, 2)
    ]);

    saveTran([
        domain => $domain,
        from => $from_address,
        to => $to_address,
        amount => $amount,
        fee => $fee,
        key => $key,
        next_hash => $next_hash,
        time => time(),
    ]);
}


function getDomains($address = null, $search_text = null, $limit = 20, $page = 0)
{
    $sql = "select distinct `domain` from accounts where 1=1";
    if ($address != null) {
        $sql .= " and `address` = '$address'";
    }
    if ($search_text != null && $search_text != "") {
        $sql .= " and `domain` like '$search_text%'";
    }
    $sql .= " limit " . $page * $limit . ", $limit";
    return selectList($sql);
}
