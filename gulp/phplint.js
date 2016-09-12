'use strict';

var config = require('./config');

var gulp = require('gulp');
var phplint = require('gulp-phplint');

gulp.task('phplint', function() {
  return gulp.src([config.bin + '/**/*.php', config.src + '/**/*.php', config.tests + '/**/*.php'])
    .pipe(phplint('', { statusLine: false, skipPassedFiles: true }))
    .pipe(phplint.reporter('fail'));
});
