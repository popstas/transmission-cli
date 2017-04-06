```
composer install
npm install -g conventional-changelog-lint
```

### Required checks for pass build:
```
./vendor/bin/phpcs --standard=psr2 ./src ./tests
phpunit
```

### .git/hooks/pre-commit
```
#!/bin/sh

set -e

vendor/bin/phpcs --standard=psr2 ./src ./tests
vendor/bin/phpunit
vendor/bin/phpcpd src
```

### Chore
- generate CHANGELOG.md
```
conventional-changelog -p angular -i CHANGELOG.md -s 
```
- generate command docs
```
bin/transmission-cli _docs > docs/commands.md
```

### Recommended
- support code full coverage
- check code with PHP Mess Detector:hich 
```
phpmd src/ text codesize,controversial,design,naming,unusedcode
```
