<?php
require_once $_SERVER["DOCUMENT_ROOT"] . "/mfm-db/utils.php";
require_once $_SERVER["DOCUMENT_ROOT"] . "/mfm-analytics/utils.php";

$gas_domain = get_config_required(gas_domain);
const genesis_address = "owner";

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

function tokenTrans($domain, $from_address, $to_address, $page, $size)
{
    $sql = "select * from trans where 1 = 1";
    if ($from_address != null)
        $sql .= " and (`from` = '$from_address' or `to` = '$from_address')";
    if ($to_address != null)
        $sql .= " and (`from` = '$to_address' or `to` = '$to_address')";
    if ($domain != null)
        $sql .= " and `domain` = '$domain'";
    $sql .= " order by time desc limit " . $page * $size . ", $size";
    return select($sql);
}

function tokenFirstTran($domain)
{
    return selectRow("select * from `trans` where `domain` = '$domain' and `from` = '" . genesis_address . "' order by `time` limit 1");
}

function tokenSecondTran($domain)
{
    return selectRow("select * from `trans` where `domain` = '$domain' and `from` = '" . genesis_address . "' order by `time` limit 1, 1");
}

function tokenTran($next_hash)
{
    return selectRow("select * from `trans` where `next_hash` = '$next_hash'");
}

function tokenLastTran($domain, $from_address, $to_address = null)
{
    // TODO add token cache check before

    $sql = "select * from `trans` where `domain` = '$domain'";
    if ($from_address != null)
        $sql .= " and `from` = '$from_address'";
    if ($to_address != null)
        $sql .= " and `to` = '$to_address'";
    $sql .= " order by `id` desc limit 1";
    return selectRow($sql);
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
    return requestEquals("/mfm-token/send.php", [
        domain => $domain,
        from_address => genesis_address,
        to_address => $address,
        amount => "$amount",
        pass => ":" . tokenNextHash($domain, $address, $password),
    ]);
}

function tokenRegScript($domain, $address, $script)
{
    if (getAccount($domain, $address) == null) {
        return requestEquals("/mfm-token/send.php", [
            domain => $domain,
            from_address => genesis_address,
            to_address => $address,
            amount => "0", // TODO если отправить 0 то ошибка
            pass => ":" . md5(random_id()),
            delegate => $script,
        ]);
    } else {
        return false;
    }
}

// ???
function tokenDelegate($domain, $address, $pass, $script)
{   ///!!!!! dont $pass  !!!!!!!!!!
    if (getAccount($domain, $address) != null) {
        return requestEquals("/mfm-token/send.php", [
            domain => $domain,
            from_address => genesis_address,
            to_address => $address,
            amount => "0", // TODO если отправить 0 то ошибка
            pass => $pass,
//            delegate => $script,
        ]);
    } else {
        return false;
    }
}

function tokenUndelegate($domain, $address)
{
    if (getAccount($domain, $address) != null) {
        return requestEquals("/mfm-token/send.php", [
            domain => $domain,
            from_address => genesis_address,
            to_address => $address,
            amount => "0", // TODO если отправить 0 то ошибка
            pass => ":",
            delegate => "",
        ]);
    } else {
        return false;
    }
}

function tokenChangePass($domain, $address, $pass)
{
    tokenSend($domain, $address, $address, 0, $pass);
}

function requestAccount($domain, $address)
{
    return http_post("/mfm-token/account.php", [
        domain => $domain,
        address => $address,
    ]);
}

function tokenSendAndCommit($domain, $from, $to, $amount, $password)
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
    if ($GLOBALS[mfm_accounts] != null) {
        $total_insert_count = 0;
        foreach ($GLOBALS[mfm_accounts] as $domain => $accounts) {
            $domain_insert_count = 0;
            foreach ($accounts as $address => $account) {
                $commit = $account[commit];
                unset($account[commit]);
                if ($commit == insert) {
                    insertRow(accounts, $account);
                    $domain_insert_count++;
                    $total_insert_count++;
                } else if ($commit == update) {
                    updateWhere(accounts, $account, [domain => $domain, address => $address]);
                }
            }
            trackAccumulate($domain . _accounts, $domain_insert_count);
        }
        trackAccumulate(accounts_count, $total_insert_count);
        $GLOBALS[mfm_accounts] = null;
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
    $GLOBALS[mfm_accounts][$domain][$address] = $account;
}

// todo change Address to Account and in schema
function getAccount($domain, $address)
{
    if ($GLOBALS[mfm_accounts] == null) {
        $GLOBALS[mfm_accounts] = [];
    }
    if ($GLOBALS[mfm_accounts][$domain] == null) {
        $GLOBALS[mfm_accounts][$domain] = [];
    }
    $account = $GLOBALS[mfm_accounts][$domain][$address];
    if ($account == null) {
        $account = selectRowWhere(accounts, [domain => $domain, address => $address]);
    }
    $GLOBALS[mfm_accounts][$domain][$address] = $account;
    return $account;
}

function saveTran($tran)
{
    if ($GLOBALS[mfm_token_trans] == null) {
        $GLOBALS[mfm_token_trans] = [];
    }
    $GLOBALS[mfm_token_trans][] = $tran;
}

function commitTrans()
{
    if ($GLOBALS[mfm_token_trans] != null) {
        $trans_in_insert_sequence = array_reverse($GLOBALS[mfm_token_trans]);
        foreach ($trans_in_insert_sequence as $tran) {
            insertRow(trans, $tran);
            broadcast(transactions, $tran);
            trackAccumulate($tran[domain] . _trans);
        }
        trackAccumulate(trans_count, count($trans_in_insert_sequence));
        $GLOBALS[mfm_token_trans] = null;
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
    if ($from_address == $to_address) error("from_address and to_address are the same");
    if ($pass != null) {
        $key = explode(":", $pass)[0];
        $next_hash = explode(":", $pass)[1];
    }
    if ($amount != round($amount, 2)) error("amount tick is 0.01");
    if ($amount < 0) error("amount less than 0");
    if ($from_address == genesis_address) {
        if (strlen($domain) < 3 || strlen($domain) > 16) error("domain length has to be between 3 and 16");
        if (tokenBalance($domain, genesis_address) === null) {
            setAccount($domain, genesis_address, [
                prev_key => "",
                next_hash => "",
                balance => $amount,
                delegate => "mfm-token/send.php",
            ]);
            if (scalarWhere(tokens, owner, [domain => $domain]) == null && $amount > 0) {
                insertRow(tokens, [
                    domain => $domain,
                    owner => $to_address,
                    amount => $amount,
                    created => time(),
                ]);
                trackAccumulate(tokens_count);
            }
        }
        $gas_domain = get_required(gas_domain);
        $gas_account = getAccount($gas_domain, $to_address);
        if ($domain != $gas_domain && $gas_account[delegate] != null) {
            $delegate = $gas_account[delegate];
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
        if ($from[delegate] != getScriptPath())
            error("script " . getScriptPath() . " cannot use $from_address address. Only " . $from[delegate]);
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

    $fee = 0;
    /*$first_tran = tokenFirstTran($domain);
        $owner_address = $first_tran[to];
        $owner = getAccount($domain, $owner_address);
        if ($owner != null
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
        delegate => $delegate,
        time => time(),
    ]);

    return $next_hash;
}


function getAccounts($address = null, $limit = 20, $page = 0)
{
    return select("select * from accounts t1"
        . " left join tokens t2 on t1.domain = t2.domain"
        . " where `address` = '$address'"
        . " limit " . $page * $limit . ", $limit");
}


function commitTokens()
{
    commitAccounts();
    commitTrans();
}