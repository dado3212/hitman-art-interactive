<template>
    <div
        class="content"
        style="background: url('https://media.hitmaps.com/img/hitman3/backgrounds/menu_bg.jpg') no-repeat center center fixed; background-size: cover"
    >
        <header class="row">
            <div class="col text-center site-header">
                <img v-webp src="/img/png/logos/hitmaps.png" class="img-fluid">
                <h1>{{ $t('interactive-maps-for-hitman') }}</h1>
            </div>
        </header>
        <div class="row loading" v-if="games.length === 0">
            <div class="loader">
                <loader></loader>
            </div>
        </div>
        <template v-if="games.length > 0">
            <div class="row dashboard">
                <div
                    class="game col-lg"
                    v-for="game in games.filter(x => ['hitman', 'hitman2', 'hitman3'].includes(x.slug))"
                    :key="game.id"
                    v-bind:style="{
                        backgroundImage:
                            'url(' + game.tileUrl + ')',
                        backgroundSize: 'cover',
                        backgroundPosition: 'center',
                        backgroundRepeat: 'no-repeat'
                    }"
                >
                    <router-link
                        :to="{ name: 'level-select', params: { slug: game.slug } }"
                    >
                        <p>&nbsp;</p>
                        <div class="game-info">
                            <div class="image">
                                <game-icon :icon="game.icon" font-style="normal" />
                            </div>
                            <div class="text">
                                <h2>{{ $t("game-type." + game.type) }}</h2>
                                <h1>{{ game.fullName }}</h1>
                            </div>
                        </div>
                    </router-link>
                </div>
            </div>
        </template>
    </div>
</template>

<script>
import Loader from '../components/Loader.vue'
import CxltToaster from 'cxlt-vue2-toastr'
import 'cxlt-vue2-toastr/dist/css/cxlt-vue2-toastr.css'
import Vue from 'vue'
import Modal from "../components/Modal";
import Alert from "../components/Alert";
import MetaHandler from "../components/MetaHandler";
import GameIcon from "../components/GameIcon";
import GameButton from "../components/GameButton";

Vue.use(CxltToaster)
export default {
    name: 'home',
    pageTitle: 'Home',
    components: {
        GameButton,
        GameIcon,
        Alert,
        Modal,
        Loader
    },
    metaInfo: {
        meta: [
            {
                name: 'description',
                content: 'HITMAPS™ provides interactive maps for the Hitman series since 2018'
            },
            {
                property: 'og:description',
                content: 'HITMAPS™ provides interactive maps for the Hitman series since 2018'
            },
            {
                property: 'twitter:description',
                content: 'HITMAPS™ provides interactive maps for the Hitman series since 2018'
            }
        ]
    },
    data() {
        return {
            games: [],
        }
    },
    methods: {
    },
    created: function() {
        MetaHandler.setOpengraphTag('image', 'https://media.hitmaps.com/img/hitmaps-custom/promo1.png');

        this.$http.get(this.$domain + '/api/web/home').then(resp => {
            this.games = resp.data.games;
        }).catch(err => {
            console.error(err);
            this.$router.push({ name: '500' });
        });
    }
}
</script>
<style lang="scss" scoped>
header {
    .site-header {
        h1 {
            margin-top: 20px;
        }

        img {
            max-height: 100px;
        }
    }
}

@media (min-width: 992px) {
    .game {
        height: 500px;
    }
}

@media (max-width: 991px) {
    .game {
        height: 300px;
        margin-bottom: 20px;
    }
}

.loading {
    .loader {
        margin: 100px auto 0;
    }
}

.dashboard {
    margin: 40px;

    .game {
        display: flex;
        flex-direction: column;
        color: white;
        margin-left: 3px;
        margin-right: 3px;

        &:hover {
            .game-info,
            .elusive-target-info {
                color: $card-footer-text;
                background: $card-footer-background-hover;

                h2 {
                    color: $card-footer-text;
                }

                .image {
                    i {
                        color: $card-footer-background-hover;
                        background: $card-footer-text;

                        &.fa-discord {
                            background: inherit;
                            color: $card-footer-text;
                        }
                    }

                    img {
                        &.normal {
                            display: none;
                        }

                        &.inverted {
                            display: block;

                        }
                    }
                }
            }
        }

        .game-info {
            padding: 15px;
            background: $card-footer-background;
            color: $card-footer-text;
            text-shadow: none;

            h2 {
                color: $card-footer-text;
                font-weight: 400;
            }

            .image {
                display: inline-block;
                vertical-align: top;
                margin-right: 5px;

                &.elusive-notification {
                    margin-right: 0;
                }

                img {
                    height: 48px;
                    width: 48px;

                    &.normal {
                        display: block;
                    }

                    &.inverted {
                        display: none;
                    }
                }


                i {
                }
            }

            .text {
                display: inline-block;
                text-transform: uppercase;

                h1 {
                    font-size: 1.5rem;
                    margin-bottom: 0;
                }

                h2 {
                    font-size: 1rem;
                    margin-bottom: 0;
                }
            }
        }
    }

    .game > a {
        display: flex;
        flex-direction: column;
        height: 100%;
        margin-left: -15px;
        margin-right: -15px;
        text-decoration: none;

        > p:first-child {
            flex-grow: 1;
        }
    }

}

</style>
