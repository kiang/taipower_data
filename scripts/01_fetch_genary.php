<?php
$basePath = dirname(__DIR__);
$text = exec("curl 'https://www.taipower.com.tw/d006/loadGraph/loadGraph/data/genary.json' \
  -H 'accept: */*' \
  -H 'accept-language: zh-TW,zh;q=0.9,en-US;q=0.8,en;q=0.7' \
  -H 'cache-control: no-cache' \
  -H 'pragma: no-cache' \
  -H 'priority: u=1, i' \
  -H 'referer: https://www.taipower.com.tw/d006/loadGraph/loadGraph/genshx_.html' \
  -H 'sec-ch-ua: \"Chromiu\";v=\"130\", \"Google Chrome\";v=\"130\", \"Not?A_Brand\";v=\"99\"' \
  -H 'sec-ch-ua-mobile: ?0' \
  -H 'sec-ch-ua-platform: \"Linux\"' \
  -H 'sec-fetch-dest: empty' \
  -H 'sec-fetch-mode: cors' \
  -H 'sec-fetch-site: same-origin' \
  -H 'user-agent: Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36' \
  -H 'x-requested-with: XMLHttpRequest'");

file_put_contents($basePath . '/docs/genary.json', json_encode(json_decode($text), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
