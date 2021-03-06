"use strict";

/**
 * User Module
 *
 * Hooks:
 * @call sigin-success
 * @call sigin-error
 * @call sigout-success
 * @call sigout-error
 *
 * @type {Nerve.Module}
 */
FeedBox.Module.User = new Nerve.Module({
    Model: Backbone.Model.extend({
        url: BASE_URL + '/login',
        defaults: {
            user: null,
            client: null,
            token: null,
            loggedIn: false,
            authRequired: undefined
        },
        initialize: function () {
            var client = localStorage['client'],
                data = {
                    user: localStorage['user'],
                    token: localStorage['token']
                };

            if (client) {
                data.client = client;
            } else {
                data.client = Math.uuid();
                localStorage['client'] = data.client;
            }

            data.loggedIn = (data.user && data.token);
            if (data.loggedIn) {
                data.authRequired = true;
            }
            this.set(data);
        },
        accessNeeded: function() {
            var that = this;

            if (this.get('authRequired') === undefined) {
                Backbone.$.ajax({
                    url: BASE_URL + '/login',
                    type: 'POST',
                    dataType: 'json',
                    async: false,
                    success: function(data, textStatus, jqXHR) {
                        that.set({
                            'authRequired': false,
                            'loggedIn': true
                        });
                    },
                    error: function(jqXHR, textStatus, errorThrown) {
                        that.set({
                            'authRequired': true,
                            'loggedIn': false
                        });
                    }
                });
            }

            return this.get('authRequired');
        },
        accessHeader: function(user, password) {
            var header = {
                'X-FeedBox-Client': this.get('client')
            };

            if (user && password) {
                header['X-FeedBox-User'] = user;
                header['X-FeedBox-Pass'] = password;
            } else {
                header['X-FeedBox-User'] = this.get('user');
                header['X-FeedBox-Token'] = this.get('token');
            }

            return header;
        },
        applySetup: function(clear) {
            var setup = {
                statusCode: {
                    401: function() {
                        var user = FeedBox.Session.get('user');
                        if (user && user.get('loggedIn')) {
                            user.signout();
                        }
                    },
                    403: function() {
                        var user = FeedBox.Session.get('user');
                        if (user && user.get('loggedIn')) {
                            user.signout();
                        }
                    }
                }
            };

            if (clear === undefined) {
                setup.headers = this.accessHeader();
            }

            Backbone.$.ajaxSetup(setup);
        },
        signin: function(user, password) {
            var that = this;

            Backbone.$.ajax({
                url: BASE_URL + '/login',
                type: 'POST',
                dataType: 'json',
                headers: this.accessHeader(user, password),
                success: function(data, textStatus, jqXHR) {
                    var token = jqXHR.getResponseHeader('X-FeedBox-Next-Token');

                    if (token) {
                        localStorage['user'] = user;
                        localStorage['token'] = token;
                        that.set({
                            user: user,
                            token: token,
                            loggedIn: true
                        }, { silent: true });

                        that.applySetup();

                        FeedBox.Hook.call('signin-success');
                        that.trigger('change:loggedIn');
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    FeedBox.notify('Username or password wrong.', 'error');
                    FeedBox.Hook.call('signin-error');
                }
            });

            return this;
        },
        signout: function() {
            var that = this;

            Backbone.$.ajax({
                url: BASE_URL + '/logout',
                type: 'POST',
                dataType: 'json',
                data: {
                    user: this.get('user'),
                    client: this.get('client')
                },
                success: function(data, textStatus, jqXHR) {
                    localStorage.removeItem('user');
                    localStorage.removeItem('token');
                    that.set({ user: null, token: null, loggedIn: false }, { silent: true });
                    that.applySetup(true);
                    FeedBox.Hook.call('signout-success');
                    that.trigger('change:loggedIn');
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    localStorage.removeItem('user');
                    localStorage.removeItem('token');
                    that.set({ user: null, token: null, loggedIn: false }, { silent: true });
                    that.applySetup(true);
                    FeedBox.Hook.call('signout-error');
                    that.trigger('change:loggedIn');
                }
            });

            return this;
        }
    }),
    initialize: function(App) {
        App.Session.set('user', new App.Module.User.Model());
    }
});
