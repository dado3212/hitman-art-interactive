<template>
    <nav class="navbar navbar-fixed-right navbar-dark">
        <div class="mobile-header">
            <div class="logo">
                <router-link :to="{ name: 'home' }">
                    <img src="/img/png/logos/hitmaps.png" class="img-fluid d-block d-md-none" alt="HITMAPS Logo"/>
                </router-link>
            </div>
            <div class="header-buttons">
                <button class="navbar-toggler"
                        type="button"
                        data-toggle="collapse"
                        data-target="#search-item-toggle"
                        aria-controls="search-item-toggle"
                        aria-expanded="true"
                        aria-label="Toggle search box">
                    <i class="fas fa-fw fa-search"></i>
                </button>
                <button class="navbar-toggler"
                        type="button"
                        data-toggle="collapse"
                        data-target="#floors-and-layers-toggle"
                        aria-controls="floors-and-layers-toggle"
                        aria-expanded="false"
                        aria-label="Toggle floors and layers box">
                    <i class="far fa-fw fa-layer-group"></i>
                </button>
                <button class="navbar-toggler"
                        type="button"
                        :aria-label="$t('language-modal.change-language')"
                        data-toggle="modal"
                        data-target="#locale-modal"
                        v-tooltip:bottom="$t('language-modal.change-language')">
                    <country-flag :country="countryFlag"/>
                </button>
            </div>
        </div>
        <div class="navbar-collapse">
            <div class="header d-none d-md-block">
                <router-link :to="{ name: 'home' }">
                    <img src="/img/png/logos/hitmaps.png" class="img-fluid" alt="HITMAPS Logo"/>
                </router-link>
            </div>
            <editor-header v-if="editorState !== 'OFF' && editorState !== 'MENU'" :editor-state="editorState"/>
            <div class="map-control d-none d-md-flex">
                <div id="map-control-buttons">
                    <control-button @click="$emit('zoom-in')" :class="currentZoomLevel === maxZoomLevel ? 'disabled' : ''">+</control-button>
                    <control-button @click="$emit('zoom-out')" :class="currentZoomLevel === minZoomLevel ? 'disabled' : ''">-</control-button>
                </div>
                <div class="control-buttons">
                    <control-button data-toggle="modal" data-target="#locale-modal" v-tooltip:bottom="$t('language-modal.change-language')">
                        <country-flag :country="countryFlag"/>
                    </control-button>
                    <template v-if="loggedIn">
                        <control-button v-if="showDebug()"
                                        id="debug-button"
                                        @click="debugMode = !debugMode"
                                        v-tooltip:top="$t('map.debug-mode')"
                                        :style="debugMode ? 'background: white; color: black' : ''">
                            <i class="fas fa-bug"></i>
                        </control-button>
                        <control-button
                            id="edit-button"
                            @click="$emit('master-edit-toggle')"
                            v-tooltip:top="$t('map.edit-map')">
                            <i class="fas fa-pencil-alt"></i>
                        </control-button>
                        <control-button
                            v-tooltip:top="$t('authentication.log-out')"
                            @click="logout">
                            <i class="fas fa-sign-out-alt"></i>
                        </control-button>
                    </template>
                    <a v-else @click="beginDiscordLogin">
                        <control-button>
                            <i class="fas fa-sign-in-alt"></i>
                        </control-button>
                    </a>
                </div>
            </div>
            <div v-show="editorState === 'OFF'">
                <div class="navbar-collapse collapse show" id="search-item-toggle">
                    <item-search :searchable-nodes="getSearchableNodes()" @search-item="onSearchItem" />
                </div>
                <div class="collapse navbar-collapse" id="floors-and-layers-toggle">
                    <hide-select-all @hide-all="$emit('hide-all')" @show-all="$emit('show-all')" />
                    <top-level-category-card v-for="topLevelCategory in topLevelCategories"
                                             :key="topLevelCategory"
                                             :top-level-category-name="topLevelCategory"
                                             :categories="categories.filter(x => x.type === topLevelCategory)"
                                             :nodes="nodes.filter(x => x.type === topLevelCategory)"
                                             @hide-category="onHideCategory"
                                             @show-category="onShowCategory"
                                             @hide-top-level-category="onHideTopLevelCategory"
                                             @show-top-level-category="onShowTopLevelCategory" />
                </div>
            </div>
            <edit-landing v-if="editorState === 'MENU'"
                          :nodes-only="mission.missionType === 'Sniper Assassin'"
                          @launch-editor="onLaunchEditor" />
            <edit-items v-if="editorState === 'ITEMS'"
                        @launch-editor="onLaunchEditor" />
        </div>
    </nav>
</template>

<script>
import LanguageHelpers from "../../LanguageHelpers";
import ControlButton from "./ControlButton";
import HideSelectAll from "./HideSelectAll";
import TopLevelCategoryCard from "./TopLevelCategoryCard";
import ItemSearch from "./ItemSearch";
import EditLanding from "./Editing/EditLanding";
import EditorHeader from "./Editing/EditorHeader";
import EditItems from "./Editing/EditItems";
import FloorToggle from "../FloorToggle";
import GameIcon from "../../GameIcon";
import Alert from "../../Alert.vue";

export default {
    name: "Sidebar",
    components: {
        Alert,
        GameIcon,
        FloorToggle,
        EditItems,
        EditorHeader,
        EditLanding, ItemSearch, TopLevelCategoryCard, HideSelectAll, ControlButton},
    props: {
        game: Object,
        mission: Object,
        loggedIn: Boolean,
        categories: Array,
        nodes: Array,
        topLevelCategories: Array,
        maxZoomLevel: Number,
        minZoomLevel: Number,
        currentZoomLevel: Number,
        editorState: String,
        drawingActive: Boolean,
    },
    data() {
        return {
            debugMode: false
        }
    },
    watch: {
        zoomControl: function() {
            console.log(this.zoomControl);
        }
    },
    computed: {
        countryFlag() {
            return LanguageHelpers.getCountryFlagForLocale(this.$i18n);
        }
    },
    methods: {
        showDebug() {
            return process.env.VUE_APP_SHOW_DEBUG === 'true';
        },
        getSearchableNodes() {
            const nodesToSort = [...this.nodes];
            nodesToSort.sort((first, second) => {
                if (first.type !== second.type) {
                    // Different types, sort by the types
                    return this.topLevelCategories.indexOf(first.type) < this.topLevelCategories.indexOf(second.type) ?
                        -1 :
                        1;
                }

                // Same type, check categories
                const firstCategory = this.categories.find(category => category.type === first.type && category.group === first.group);
                const secondCategory = this.categories.find(category => category.type === second.type && category.group === second.group);

                if (firstCategory.order !== secondCategory.order) {
                    return firstCategory.order < secondCategory.order ? -1 : 1;
                }

                return first.name < second.name ? -1 : 1;
            });

            const uniqueNodes = nodesToSort.filter(x => x.searchable) // Only searchable items
                .map(x => `${x.group}|${x.name}`) // Only get the group and name
                .filter((value, index, self) => self.indexOf(value) === index); // Only fetch unique records
            const uniqueNodesBrokenDownByGroup = {};
            uniqueNodes.forEach(node => {
                const nodeInfo = node.split('|');
                if (!(nodeInfo[0] in uniqueNodesBrokenDownByGroup)) {
                    uniqueNodesBrokenDownByGroup[nodeInfo[0]] = [];
                }
                uniqueNodesBrokenDownByGroup[nodeInfo[0]].push(nodeInfo[1]);
            });

            return uniqueNodesBrokenDownByGroup;
        },
        beginDiscordLogin() {
            // Temporary hack
            this.$http.post(`${this.$domain}/api/web/user/login`, {
                tokenType: 'df',
                accessToken: 'df'
            })
                .then(e => {
                    this.operationInProgress = false;
                    this.$toast.success({
                        message: 'Logged in!'
                    });
                    location.reload();
                }).catch(e => {
                    this.operationInProgress = false;
                    this.$toast.error({
                        message: e
                    });
                });
            // this.$cookies.set('redirect-location', `${window.location.pathname}${window.location.search}`, '600s');
            // window.location.href = `https://discordapp.com/api/oauth2/authorize?client_id=681919936469401687&redirect_uri=${encodeURIComponent(this.$vueDomain)}/auth&response_type=token&scope=connections%20identify%20guilds%20email`;
        },
        logout: function() {
            localStorage.removeItem('token');
            location.reload();
        },
        //region Event listeners
        onSearchItem(searchedItem) {
            this.$emit('search-item', searchedItem);
        },
        onHideCategory(category) {
            this.$emit('hide-category', category);
        },
        onShowCategory(category) {
            this.$emit('show-category', category);
        },
        onHideTopLevelCategory(type) {
            this.$emit('hide-top-level-category', type);
        },
        onShowTopLevelCategory(type) {
            this.$emit('show-top-level-category', type);
        },
        onLaunchEditor(editor) {
            this.$emit('launch-editor', editor);
        },
        onChangeFloor(floorNumber) {
            this.$emit('change-floor', floorNumber);
        },
        //endregion
    }
}
</script>

<style lang="scss" scoped>
    @media (min-width: 768px) {
        .mobile-header {
            display: none;
        }

        .navbar-fixed-right {
            display: block;
            float: right;
            width: 400px;
            min-height: 100vh;
            background: rgba(0, 0, 0, .90);
        }

        .navbar-toggler {
            display: none;
        }

        .navbar-collapse {
            display: block !important;
        }

        .accordion > .floor-toggle {
            display: none;
        }

        .content > .floor-toggle {
            display: block;
            position: fixed;
            top: 110px;
            left: 50px;
        }
    }

    @media (max-width: 767px) {
        .mobile-header {
            display: flex;
            margin-bottom: 5px;

            .logo {
                img {
                    height: 44px;
                }
            }

            .header-buttons {
                display: flex;
                flex-grow: 1;
                justify-content: flex-end;
            }
        }

        .navbar {
            display: block;
        }

        .navbar-fixed-right {
            background: rgba(0, 0, 0, .90);
        }

        .navbar-toggler {
            display: block;

            i {
                font-size: 16px;
            }

            &[data-toggle="collapse"] {
                padding: 10px;
            }

            & + & {
                margin-left: 10px;
            }
        }

        .accordion > .floor-toggle {
            display: block;
            margin-bottom: 10px;
        }

        .content > .floor-toggle {
            display: none;
        }
    }

    .navbar {
        z-index: 2;
    }

    .navbar-toggler {
        background: rgba(22, 24, 29, .75);
        border: 2px solid #2a2d31;
        color: #fff;
        border-radius: 3px;
        transition: color .15s ease-in-out,
                    background-color .15s ease-in-out,
                    border-color .15s ease-in-out,
                    box-shadow .15s ease-in-out;
        opacity: 0.85;

        &:hover {
            opacity: 1;
            border-color: #fff;
        }
    }

    .map-control {
        width: 368px;
        display: flex;

        &.debug {
            flex-direction: column;
        }

        .control-buttons {
            display: flex;
            justify-content: flex-end;
            flex-grow: 1;
            margin-bottom: 10px;

            .control-button:not(:last-child) {
                margin-right: 5px;
            }
        }

        #map-control-buttons {
            .control-button {
                line-height: 26px;
                width: 40px;
                margin-left: 0;
                margin-right: 5px;
                margin-bottom: 10px;
                font: bold 18px 'Lucida Console', Monaco, monospace;
                text-indent: 1px;
                text-align: center;
            }
        }
    }

    .header {
        max-width: 368px;
        margin-top: 20px;
        margin-bottom: 20px;

        a {
            opacity: 0.85;

            &:hover {
                opacity: 1;
            }
        }
    }
</style>