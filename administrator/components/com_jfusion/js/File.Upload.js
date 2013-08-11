/*

 name: [File.Upload, Request.File]
 description: Ajax file upload with MooTools.
 license: MIT-style license
 author: Matthew Loberg
 requires: [Request]
 provides: [File.Upload, Request.File]
 credits: Based off of MooTools-Form-Upload (https://github.com/arian/mootools-form-upload/) by Arian Stolwijk

 */
if (typeof File === 'undefined') {
    var File = {};
}

File.Upload = new Class({

    Implements: [Options, Events],

    options: {
        onComplete: function () {
            //undefined default function
        }, onException : function () {
            //undefined default function
        }
    },

    initialize: function (options) {
        var self = this;
        this.setOptions(options);
        this.uploadReq = new Request.File({
            onComplete: function () {
                self.fireEvent('complete', arguments);
                this.reset();
            }, onException: function () {
                self.fireEvent('exception', arguments);
            }
        });
        this.uploadReq.setOptions(options);
        if (this.options.data) {
            this.data(this.options.data);
        }
        if (this.options.images) {
            this.addMultiple(this.options.images);
        }
    },

    data: function (data) {
        var self = this;
        if (this.options.url.indexOf('?') < 0) {
            this.options.url += '?';
        }
        Object.each(data, function (value, key) {
            if (self.options.url.charAt(self.options.url.length - 1) !== '?') {
                self.options.url += '&';
            }
            self.options.url += encodeURIComponent(key) + '=' + encodeURIComponent(value);
        });
    },

    addMultiple: function (inputs) {
        var self = this;
        inputs.each(function (input) {
            self.add(input);
        });
    },

    add: function (id) {
        var input, name, file;
        input = $(id);
        if (input.files) {
            name = input.get('name');
            file = input.files[0];
            this.uploadReq.append(name, file);
        }
    },

    send: function (input) {
        if (input) {
            this.add(input);
        }
        this.uploadReq.send({
            url: this.options.url
        });
    }
});

Request.File = new Class({

    Extends: Request,

    options: {
        emulation: false,
        urlEncoded: false
    },

    initialize: function (options) {
        this.xhr = new Browser.Request();
        this.setOptions(options);
        this.headers = this.options.headers;
        this.reset();
    },

    append: function (key, value) {
        if (this.formData) {
            this.formData.append(key, value);
        }
    },

    reset: function () {
        try {
            this.formData = new FormData();
        } catch (e) {
            this.formData = false;
            this.fireEvent('exception', e);
        }
    },

    send: function (options) {
        var url, xhr;
        if (this.formData) {
            url = options.url || this.options.url;

            if (this.options.format) {
                var format = 'format=' + this.options.format;
                url = (url) ? url + '&' + format : format;
            }

            if (this.options.noCache) {
                var noCache = 'noCache=' + new Date().getTime();
                url = (url) ? url + '&' + noCache : noCache;
            }

            this.options.isSuccess = this.options.isSuccess || this.isSuccess;
            this.running = true;

            xhr = this.xhr;
            xhr.open('POST', url, this.options.async);
            xhr.onreadystatechange = this.onStateChange.bind(this);

            Object.each(this.headers, function (value, key) {
                try {
                    xhr.setRequestHeader(key, value);
                } catch (e) {
                    this.fireEvent('exception', [key, value]);
                }
            }, this);

            this.fireEvent('request');
            xhr.send(this.formData);

            if (!this.options.async) {
                this.onStateChange();
            }
            if (this.options.timeout) {
                this.timer = this.timeout.delay(this.options.timeout, this);
            }
        }
        return this;
    }
});