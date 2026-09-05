import { useState, useEffect, useCallback } from 'react';

const API_BASE = '/api';

/**
 * Hook for fetching channels with search, filter, and sorting
 */
export function useChannels(options = {}) {
    const { search = '', filter = 'all', sortBy = 'status', autoRefresh = false, refreshInterval = 30000, forceRefreshOnMount = false, detectionInterval = 300000 } = options;
    // detectionInterval: how often to trigger YouTube detection (default 5 minutes)

    const [channels, setChannels] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const [lastUpdated, setLastUpdated] = useState(null);
    const [lastDetectionTime, setLastDetectionTime] = useState(0);

    const fetchChannels = useCallback(async (forceRefresh = false) => {
        try {
            setError(null);
            // Force refresh triggers YouTube detection
            const url = forceRefresh
                ? `${API_BASE}/channels?refresh=true`
                : `${API_BASE}/channels`;
            const response = await fetch(url);
            if (!response.ok) {
                throw new Error(`Failed to fetch channels: ${response.status}`);
            }
            const data = await response.json();
            setChannels(data.data || []);
            setLastUpdated(new Date());
        } catch (err) {
            setError(err.message);
        } finally {
            setLoading(false);
        }
    }, []);

    // Initial fetch - with detection on mount if requested
    useEffect(() => {
        if (forceRefreshOnMount) {
            fetchChannels(true);
            setLastDetectionTime(Date.now());
        } else {
            fetchChannels(false);
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []); // Only run once on mount

    // Auto refresh
    useEffect(() => {
        if (!autoRefresh) return;

        const interval = setInterval(() => {
            const now = Date.now();
            const shouldDetect = now - lastDetectionTime >= detectionInterval;

            if (shouldDetect) {
                setLastDetectionTime(now);
            }

            fetchChannels(shouldDetect);
        }, refreshInterval);

        return () => clearInterval(interval);
    }, [autoRefresh, refreshInterval, fetchChannels, lastDetectionTime, detectionInterval]);

    // Filter channels
    const filteredChannels = channels.filter(channel => {
        // Search filter
        if (search) {
            const searchLower = search.toLowerCase();
            const matchesSearch =
                channel.channel_name?.toLowerCase().includes(searchLower) ||
                channel.youtube_channel_id?.toLowerCase().includes(searchLower) ||
                channel.channel_url?.toLowerCase().includes(searchLower);
            if (!matchesSearch) return false;
        }

        // Status filter
        switch (filter) {
            case 'live':
                return channel.is_live === true;
            case 'offline':
                return channel.is_live === false;
            case 'monitoring_on':
                return channel.is_active === true;
            case 'monitoring_off':
                return channel.is_active === false;
            default:
                return true;
        }
    });

    // Sort channels
    const sortedChannels = [...filteredChannels].sort((a, b) => {
        switch (sortBy) {
            case 'status':
                // Live channels first
                if (a.is_live && !b.is_live) return -1;
                if (!a.is_live && b.is_live) return 1;
                return a.channel_name?.localeCompare(b.channel_name) || 0;
            case 'name':
                return a.channel_name?.localeCompare(b.channel_name) || 0;
            case 'last_checked':
                const aTime = a.last_checked_at ? new Date(a.last_checked_at).getTime() : 0;
                const bTime = b.last_checked_at ? new Date(b.last_checked_at).getTime() : 0;
                return bTime - aTime;
            case 'last_live':
                const aLive = a.last_live_at ? new Date(a.last_live_at).getTime() : 0;
                const bLive = b.last_live_at ? new Date(b.last_live_at).getTime() : 0;
                return bLive - aLive;
            default:
                return 0;
        }
    });

    // Stats
    const stats = {
        total: channels.length,
        live: channels.filter(c => c.is_live).length,
        offline: channels.filter(c => !c.is_live && c.is_active).length,
        errors: 0, // TODO: Implement error tracking if needed
        monitoringOn: channels.filter(c => c.is_active).length,
        monitoringOff: channels.filter(c => !c.is_active).length,
    };

    return {
        channels: sortedChannels,
        allChannels: channels,
        loading,
        error,
        lastUpdated,
        stats,
        refresh: fetchChannels,
    };
}

/**
 * Hook for fetching live streams only
 */
export function useLiveStreams(refreshInterval = 30000) {
    const [streams, setStreams] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const [lastUpdated, setLastUpdated] = useState(null);

    const fetchStreams = useCallback(async () => {
        try {
            setError(null);
            const response = await fetch(`${API_BASE}/channels/live`);
            if (!response.ok) {
                throw new Error(`Failed to fetch live streams: ${response.status}`);
            }
            const data = await response.json();
            setStreams(data.data || []);
            setLastUpdated(new Date());
        } catch (err) {
            setError(err.message);
        } finally {
            setLoading(false);
        }
    }, []);

    useEffect(() => {
        fetchStreams();
        const interval = setInterval(fetchStreams, refreshInterval);
        return () => clearInterval(interval);
    }, [fetchStreams, refreshInterval]);

    return { streams, loading, error, lastUpdated, refresh: fetchStreams };
}

/**
 * Hook for channel CRUD operations
 */
export function useChannelActions(onSuccess) {
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(null);

    const addChannel = async (channelInput) => {
        setLoading(true);
        setError(null);

        try {
            const response = await fetch(`${API_BASE}/channels`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ channel: channelInput }),
            });

            const data = await response.json();

            if (!response.ok) {
                const errorMessage = data.error || data.message || 'Failed to add channel';
                throw new Error(errorMessage);
            }

            if (onSuccess) onSuccess(data.channel, 'Channel added successfully');
            return data.channel;
        } catch (err) {
            setError(err.message);
            throw err;
        } finally {
            setLoading(false);
        }
    };

    const updateChannel = async (id, updates) => {
        setLoading(true);
        setError(null);

        try {
            const response = await fetch(`${API_BASE}/channels/${id}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                body: JSON.stringify(updates),
            });

            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.error || data.message || 'Failed to update channel');
            }

            if (onSuccess) onSuccess(data.channel, 'Channel updated');
            return data.channel;
        } catch (err) {
            setError(err.message);
            throw err;
        } finally {
            setLoading(false);
        }
    };

    const toggleMonitoring = async (id, isActive) => {
        return updateChannel(id, { is_active: isActive });
    };

    const deleteChannel = async (id) => {
        setLoading(true);
        setError(null);

        try {
            const response = await fetch(`${API_BASE}/channels/${id}`, {
                method: 'DELETE',
                headers: {
                    'Accept': 'application/json',
                },
            });

            if (!response.ok) {
                const data = await response.json();
                throw new Error(data.error || data.message || 'Failed to delete channel');
            }

            if (onSuccess) onSuccess(null, 'Channel removed');
            return true;
        } catch (err) {
            setError(err.message);
            throw err;
        } finally {
            setLoading(false);
        }
    };

    const checkChannel = async (id) => {
        setLoading(true);
        setError(null);

        try {
            const response = await fetch(`${API_BASE}/channels/${id}/check`, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                },
            });

            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.error || data.message || 'Failed to check channel');
            }

            return data;
        } catch (err) {
            setError(err.message);
            throw err;
        } finally {
            setLoading(false);
        }
    };

    return {
        loading,
        error,
        addChannel,
        updateChannel,
        toggleMonitoring,
        deleteChannel,
        checkChannel,
        clearError: () => setError(null),
    };
}
