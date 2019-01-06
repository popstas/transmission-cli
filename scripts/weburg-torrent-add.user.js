// ==UserScript==
// @name         Weburg.net transmission-cli torrent-add
// @namespace    http://tampermonkey.net/
// @version      0.1
// @description  download torrent
// @author       popstas
// @match        https://weburg.net/*
// @grant        none
// @updateURL    https://raw.githubusercontent.com/popstas/transmission-cli/master/scripts/weburg-torrent-add.user.js
// ==/UserScript==

(function() {
  'use strict';
  const serverBase = 'http://popstas-server:10293/';
  const u = 'undefined',
    win = typeof unsafeWindow != u ? unsafeWindow : window;
  const $ = win.$;

  win.onerror = function(error, file, line) {
    console.log(error + ' (line ' + line + ')');
  };

  if (win.top != win.self) {
    return false; // ignore iframes
  }

  const addLinks = () => {
    let links = $('.js-a__torrent:not(.js-a__transmission),.objects__torrent:not(.js-a__transmission)');
    links.each((ind, elem) => {
      const link = $(elem);
      link.addClass('js-a__transmission');
      const torrentUrl = link.attr('href');
      const sshCommandUrl = serverBase + '?url=' + encodeURIComponent(torrentUrl);
      let downloadSshLink = $(
        '<a target="_blank" style="margin-left: 10px" class="wb-torrents-dropdown-download__i">Скачать через Transmission</a>'
      ).attr('href', sshCommandUrl);
      link.after(downloadSshLink);
    });
  };

  $(document).ajaxSuccess(() => {
    setTimeout(addLinks, 500);
  });
})();
