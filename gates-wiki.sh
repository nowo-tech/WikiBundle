set -u
composer install --no-interaction -q 2>&1 | tail -3
vendor/bin/php-cs-fixer fix 2>&1 | tail -2
vendor/bin/rector process --dry-run --no-progress-bar 2>&1 | tail -3
vendor/bin/phpunit --no-progress --coverage-clover /logs/wk.xml --filter WikiManageControllerTest > /logs/wiki.txt 2>&1
grep -E "^[0-9]+\) |^Tests:|^OK" /logs/wiki.txt
php -r '$x=simplexml_load_file("/logs/wk.xml"); foreach($x->xpath("//file[contains(@name,\"WikiManageController.php\")]/line") as $l){ $n=(int)$l["num"]; if((string)$l["count"]==="0" && $n>1600 && $n<1700) echo "uncovered ".$n."\n"; }'
echo "=== done"
