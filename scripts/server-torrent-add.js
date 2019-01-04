#!/usr/bin/node

const http = require('http');
const exec = require('child_process').exec;
const os = require('os');
const url = require('url');
const querystring = require('querystring');

const port = 10293;

http
  .createServer((req, res) => {
    const urlParams = url.parse(req.url);
    const query = querystring.parse(urlParams.query);
    const torrentUrl = query.url;

    if (!torrentUrl) {
      res.writeHead(200, { 'Content-Type': 'text/html' });
      res.end('No url provided', 'utf-8');
      return;
    }

    const command = `transmission-cli torrent-add "${torrentUrl}"`;
    console.log('command: ', command);

    exec(command, function(err, stdout, stderr) {
      let content = `<pre>\n${command}\n` + stderr + stdout + '\n</pre>';
      console.log(stderr);
      console.log(stdout);
      res.writeHead(200, { 'Content-Type': 'text/html;charset=utf-8' });
      res.end(content, 'utf-8');
    });
  })
  .listen(port);

console.log(`Server running at http://${os.hostname()}:${port}/\nUsage: http://${os.hostname()}:${port}/?url=http://weburg.net/url/to/torrent`);
