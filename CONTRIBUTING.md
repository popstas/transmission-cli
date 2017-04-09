```
composer install
npm install -g conventional-changelog-lint
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
- generate CHANGELOG.md and docs/commands.md
```
scripts/generate-docs
```

### Recommended
- support code full coverage
- check code with PHP Mess Detector:hich 
```
phpmd src/ text codesize,controversial,design,naming,unusedcode
```
