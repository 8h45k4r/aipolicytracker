/* AIPolicyTracker embed loader: sizes an embedded widget frame to its content. ~1 KB, no cookies, no tracking. */
(function () {
  'use strict'
  var origin = document.currentScript && document.currentScript.src ? new URL(document.currentScript.src).origin : ''
  function fit () {
    var frames = document.querySelectorAll('iframe[src*="/embed/"]')
    for (var i = 0; i < frames.length; i++) {
      var f = frames[i]
      if (origin && f.src.indexOf(origin) !== 0) continue
      f.setAttribute('scrolling', 'auto')
      f.style.maxWidth = f.style.maxWidth || '720px'
    }
  }
  window.addEventListener('message', function (e) {
    if (origin && e.origin !== origin) return
    var d = e.data || {}
    if (d.aipEmbedHeight && typeof d.aipEmbedHeight === 'number') {
      var frames = document.querySelectorAll('iframe[src*="/embed/"]')
      for (var i = 0; i < frames.length; i++) {
        if (frames[i].contentWindow === e.source) frames[i].style.height = Math.min(2000, Math.max(120, d.aipEmbedHeight)) + 'px'
      }
    }
  })
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', fit)
  else fit()
})()
