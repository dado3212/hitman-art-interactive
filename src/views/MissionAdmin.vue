<template>
    <div
        class="content"
        style="background: url('https://media.hitmaps.com/img/hitman3/backgrounds/menu_bg.jpg') no-repeat center center fixed; background-size: cover">
        <div class="container">
            <div class="row">
                <div class="col">
                    <div class="metadata">
                        <h1>{{ missionInfo.name }}</h1>
                        <h2>{{ missionInfo.missionType }}</h2>

                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
<style lang="scss" scoped>
.content {
    .col-md-8, .col-md-4, .col {
        font-family: 'nimbus_sans_lbold', sans-serif;
        background-color: $content-background;
        color: $content-text;

        h1 {
            text-transform: uppercase;
        }
    }

    .row:not(:first-child) {
        margin-top: 10px;
    }
}
</style>

<script>
import GameButton from "../components/GameButton";
import GameIcon from "../components/GameIcon";
export default {
    name: 'mission-admin',
    components: {GameIcon, GameButton},
    pageTitle: 'Mission Admin',
    props: {
        game: String,
        location: String,
        mission: String
    },
    data() {
        return {
            missionInfo: {},
        }
    },
    mounted() {
        this.$http.get(`${this.$domain}/api/v1/games/${this.game}/locations/${this.location}/missions/${this.mission}`)
            .then(resp => this.missionInfo = resp.data[0]);
    },
    methods: {
    }
}
</script>
