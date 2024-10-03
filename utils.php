<?php
include_once $_SERVER["DOCUMENT_ROOT"] . "/mfm-data/utils.php";
include_once $_SERVER["DOCUMENT_ROOT"] . "/mfm-data/track.php";

$gas_domain = "usdt";

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

function tokenFirstTran($domain)
{
    return selectRow("select * from `trans` where `domain` = '$domain' and `from` = 'owner' order by `time` asc limit 1");
}

function tokenOwner($domain)
{
    return tokenFirstTran($domain)[to];
}

function tokenAddressBalance($domain, $address)
{
    $address = getAccount($domain, $address);
    if ($address != null) {
        return $address[balance];
    }
    return null;
}

function tokenAccountReg($domain, $address, $password, $amount = 0)
{
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

function tokenScriptReg($domain, $address, $script)
{
    if (getAccount($domain, $address) == null) {
        return requestEquals("/mfm-token/send.php", [
            domain => $domain,
            from_address => owner,
            to_address => $address,
            amount => "0", // TODO если отправить 0 то ошибка
            pass => ":",
            script => $script,
            delegate => $script,
        ]);
    } else {
        return false;
    }
}

function tokenSendAndCommit($domain, $from, $to, $password, $amount)
{
    if (getAccount($domain, $from) != null) {
        return requestEquals("/mfm-token/send.php", [
            domain => $domain,
            from_address => $from,
            to_address => $to,
            amount => "$amount",
            pass => tokenPass($domain, $from, $password),
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
        foreach ($GLOBALS[trans] as $tran) {
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
    if ($from_address == owner) {
        if (strlen($domain) < 3 || strlen($domain) > 32) error("domain length has to be between 3 and 32");
        if (tokenAddressBalance($domain, owner) === null) {
            setAccount($domain, owner, [
                prev_key => "",
                next_hash => "",
                balance => $amount,
                delegate => "mfm-token/send.php",
            ]);
        }
        if (tokenAddressBalance($domain, $to_address) === null) {
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
    if ($from[balance] < $amount) error(strtoupper($domain) . " balance is not enough in $from_address wallet");
    if ($to == null) error("$to_address receiver doesn't exist");
    if ($from[delegate] != null) {
        if ($from[delegate] != scriptPath())
            error("script " . scriptPath() . " cannot use $from_address address. Only " . $from[delegate]);
    } else {
        if ($from[next_hash] != md5($key)) error("$domain key is not right");
    }

    if ($from[delegate] != null) {
        setAccount($domain, $from_address, [
            balance => $from[balance] - $amount,
        ]);
    } else {
        setAccount($domain, $from_address, [
            balance => $from[balance] - $amount,
            prev_key => $key,
            next_hash => $next_hash,
        ]);
    }
    setAccount($domain, $to_address, [
        balance => $to[balance] + $amount
    ]);

    saveTran([
        domain => $domain,
        from => $from_address,
        to => $to_address,
        amount => $amount,
        key => $key,
        next_hash => $next_hash,
        time => time(),
    ]);
}

function spendGasOf($gas_address, $gas_password)
{
    $gas_domain = get_required(gas_domain);
    $GLOBALS[gas_pass] = tokenPass($gas_domain, $gas_address, $gas_password);
}

function commit($response = null)
{
    if ($response == null)
        $response = [];
    $response[success] = true;
    $gas_rows = 0;
    $gas_rows += count($GLOBALS[new_data]);
    $gas_rows += count($GLOBALS[new_history]);
    $gas_spent = 0.001 * $gas_rows;

    if ($gas_rows != 0) {
        tokenSend(
            $GLOBALS[gas_domain],
            get_required(gas_address),
            admin,
            $gas_spent,
            get_required(gas_pass),
        );
        commitData();
        $response[gas_spend] = $gas_spent;
    }
    commitAccounts();
    commitTrans();
    echo json_encode_readable($response);
}

